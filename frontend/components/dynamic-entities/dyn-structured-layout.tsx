"use client";

import { useEffect, useMemo, useRef, useState, type MouseEvent, type ReactNode } from "react";
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  closestCorners,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
  type UniqueIdentifier,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  rectSortingStrategy,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ChevronDown, GripVertical, Layers, Pencil, Plus, SquarePen, Trash2 } from "lucide-react";

import type { DynSchemaMode } from "@/components/dynamic-entities/dyn-schema-sheets";
import { Button } from "@/components/ui/button";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuLabel,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { cn } from "@/lib/utils";
import {
  updateDynField,
  updateDynFieldGroup,
  deleteDynFieldGroup,
  type DynEntityDetail,
  type DynField,
} from "@/lib/api/modules/dynamic-entities-api";
import { dynColumnSpanStyle } from "@/lib/dynamic-entities/form-arrange-rows";

type Group = DynEntityDetail["field_groups"][number];

type Props = {
  groups: Group[];
  fields: DynField[];
  canManageSchema: boolean;
  layoutEditing?: boolean;
  mode?: "view" | "form";
  includeUngrouped?: boolean;
  renderFieldValue: (field: DynField) => ReactNode;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onLayoutChanged: () => Promise<void> | void;
};

const UNGROUPED = "__ungrouped__";

function groupKeyForField(field: DynField, mode: "view" | "form"): string {
  // Prefer form grouping so View shows the same whole groups as Arrange Form.
  const id =
    mode === "form"
      ? (field.form_group_id ?? field.view_group_id)
      : (field.form_group_id ?? field.view_group_id);
  return id ?? UNGROUPED;
}

function SchemaMenuItems({
  field,
  group,
  onSchemaAction,
  onDeleteGroup,
}: {
  field?: DynField | null;
  group: Group | null;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteGroup?: (group: Group) => void;
}) {
  return (
    <>
      {field ? (
        <>
          <ContextMenuItem onClick={() => onSchemaAction({ kind: "edit-field", field })}>
            <SquarePen className="size-3.5 text-muted-foreground" />
            Edit field details
          </ContextMenuItem>
          <ContextMenuSeparator />
        </>
      ) : null}
      <ContextMenuLabel>Group</ContextMenuLabel>
      <ContextMenuItem
        className="text-emerald-700 dark:text-emerald-400"
        onClick={() => onSchemaAction({ kind: "new-group" })}
      >
        <Layers className="size-3.5" />
        New group
      </ContextMenuItem>
      {group ? (
        <>
          <ContextMenuItem onClick={() => onSchemaAction({ kind: "edit-group", group })}>
            <Pencil className="size-3.5 text-sky-600" />
            Edit “{group.name}”
          </ContextMenuItem>
          <ContextMenuItem destructive onClick={() => onDeleteGroup?.(group)}>
            <Trash2 className="size-3.5" />
            Delete “{group.name}”
          </ContextMenuItem>
        </>
      ) : null}
      <ContextMenuItem
        onClick={() => onSchemaAction({ kind: "add-field", groupId: group?.id ?? null })}
      >
        <Plus className="size-3.5 text-muted-foreground" />
        Add field
      </ContextMenuItem>
    </>
  );
}

export function DynStructuredLayout({
  groups,
  fields,
  canManageSchema,
  layoutEditing,
  mode = "view",
  includeUngrouped = true,
  renderFieldValue,
  onSchemaAction,
  onLayoutChanged,
}: Props) {
  const editingLayout = Boolean(layoutEditing ?? (mode === "form" && canManageSchema));
  /** Drag-and-drop rearrange (Customize / Edit). */
  const schemaTools = canManageSchema && editingLayout;
  /** Click menus + selection chrome in View and Edit when permitted. */
  const schemaMenus = canManageSchema;

  const [orderedGroups, setOrderedGroups] = useState(groups);
  const [fieldsByGroup, setFieldsByGroup] = useState<Record<string, DynField[]>>({});
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
  const [selectedFieldId, setSelectedFieldId] = useState<string | null>(null);
  const [savingLayout, setSavingLayout] = useState(false);
  const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);
  const [activeKind, setActiveKind] = useState<"group" | "field" | null>(null);
  const fieldsByGroupRef = useRef<Record<string, DynField[]>>({});
  const orderedGroupsRef = useRef(groups);
  /** Set when onDragOver moves a field across groups — onDragEnd must still persist. */
  const fieldsLayoutDirtyRef = useRef(false);

  useEffect(() => {
    setOrderedGroups(groups);
    orderedGroupsRef.current = groups;
    setCollapsed((prev) => {
      let changed = false;
      const next = { ...prev };
      for (const g of groups) {
        if (next[g.id] === undefined) {
          next[g.id] = Boolean(g.start_collapsed);
          changed = true;
        }
      }
      return changed ? next : prev;
    });
  }, [groups]);

  useEffect(() => {
    const next: Record<string, DynField[]> = {};
    for (const g of groups) next[g.id] = [];
    next[UNGROUPED] = [];
    for (const field of [...fields].sort((a, b) => a.field_order - b.field_order)) {
      const key = groupKeyForField(field, mode);
      if (!next[key]) next[key] = [];
      next[key].push(field);
    }
    fieldsByGroupRef.current = next;
    setFieldsByGroup((prev) => {
      const prevKeys = Object.keys(prev);
      const nextKeys = Object.keys(next);
      if (prevKeys.length === nextKeys.length) {
        let same = true;
        for (const key of nextKeys) {
          const a = prev[key] ?? [];
          const b = next[key] ?? [];
          if (a.length !== b.length || a.some((field, i) => field.id !== b[i]?.id)) {
            same = false;
            break;
          }
        }
        if (same) return prev;
      }
      return next;
    });
  }, [fields, groups, mode]);

  async function deleteGroup(group: Group) {
    if (
      !window.confirm(
        `Delete group “${group.name}”? Fields in this group stay on the record but move to Other fields.`,
      )
    ) {
      return;
    }
    setSavingLayout(true);
    try {
      await deleteDynFieldGroup(group.id);
      await onLayoutChanged();
    } finally {
      setSavingLayout(false);
    }
  }

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 10 } }),
  );

  const sectionKeys = useMemo(() => {
    const keys = orderedGroups.map((g) => g.id);
    const ungrouped = fieldsByGroup[UNGROUPED] ?? [];
    if (includeUngrouped && (ungrouped.length > 0 || orderedGroups.length === 0 || schemaTools)) {
      keys.push(UNGROUPED);
    }
    return keys.filter((key) => {
      const list = fieldsByGroup[key] ?? [];
      return list.length > 0 || schemaTools;
    });
  }, [orderedGroups, fieldsByGroup, includeUngrouped, schemaTools]);

  const activeField = useMemo(() => {
    if (activeKind !== "field" || !activeId) return null;
    for (const list of Object.values(fieldsByGroup)) {
      const hit = list.find((f) => f.id === activeId);
      if (hit) return hit;
    }
    return null;
  }, [activeId, activeKind, fieldsByGroup]);

  const activeGroup = useMemo(() => {
    if (activeKind !== "group" || !activeId) return null;
    return orderedGroups.find((g) => g.id === activeId) ?? null;
  }, [activeId, activeKind, orderedGroups]);

  async function persistGroupOrder(next: Group[]) {
    setSavingLayout(true);
    try {
      await Promise.all(next.map((g, index) => updateDynFieldGroup(g.id, { sort_order: (index + 1) * 10 })));
      await onLayoutChanged();
    } finally {
      setSavingLayout(false);
    }
  }

  async function persistFieldsByGroup(map: Record<string, DynField[]>) {
    setSavingLayout(true);
    try {
      const writes: Promise<unknown>[] = [];
      for (const [key, list] of Object.entries(map)) {
        const groupId = key === UNGROUPED ? null : key;
        list.forEach((f, index) => {
          const nextOrder = (index + 1) * 10;
          const sameGroup =
            (f.form_group_id ?? null) === groupId && (f.view_group_id ?? null) === groupId;
          if (sameGroup && f.field_order === nextOrder) return;
          writes.push(
            updateDynField(f.id, {
              field_order: nextOrder,
              form_group_id: groupId,
              view_group_id: groupId,
            }),
          );
        });
      }
      if (writes.length > 0) {
        await Promise.all(writes);
        await onLayoutChanged();
      }
    } catch {
      // Reload so UI matches server if a write failed mid-batch.
      await onLayoutChanged();
    } finally {
      setSavingLayout(false);
    }
  }

  function findContainer(
    id: UniqueIdentifier,
    map: Record<string, DynField[]> = fieldsByGroupRef.current,
  ): string | null {
    const asString = String(id);
    if (asString.startsWith("container:")) return asString.replace("container:", "");
    // Prefer field membership over key match so group sortable ids don't steal field lookups.
    for (const [key, list] of Object.entries(map)) {
      if (list.some((f) => f.id === asString)) return key;
    }
    if (Object.prototype.hasOwnProperty.call(map, asString)) return asString;
    return null;
  }

  function onDragStart(event: DragStartEvent) {
    const kind = event.active.data.current?.kind as "group" | "field" | undefined;
    setActiveId(event.active.id);
    setActiveKind(kind ?? null);
    fieldsLayoutDirtyRef.current = false;
  }

  function onDragOver(event: DragOverEvent) {
    const { active, over } = event;
    if (!over || active.data.current?.kind !== "field") return;

    const activeContainer = findContainer(active.id);
    const overContainer = findContainer(over.id);
    if (!activeContainer || !overContainer || activeContainer === overContainer) return;

    fieldsLayoutDirtyRef.current = true;
    setFieldsByGroup((prev) => {
      const from = [...(prev[activeContainer] ?? [])];
      const to = [...(prev[overContainer] ?? [])];
      const fromIndex = from.findIndex((f) => f.id === active.id);
      if (fromIndex < 0) return prev;
      const [moved] = from.splice(fromIndex, 1);
      const overIndex = to.findIndex((f) => f.id === over.id);
      if (overIndex >= 0) to.splice(overIndex, 0, moved);
      else to.push(moved);
      const next = { ...prev, [activeContainer]: from, [overContainer]: to };
      fieldsByGroupRef.current = next;
      return next;
    });
  }

  function onDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    setActiveId(null);
    setActiveKind(null);
    if (!schemaTools || !over) {
      fieldsLayoutDirtyRef.current = false;
      return;
    }

    const kind = active.data.current?.kind as "group" | "field" | undefined;

    if (kind === "group") {
      fieldsLayoutDirtyRef.current = false;
      const current = orderedGroupsRef.current;
      const oldIndex = current.findIndex((g) => g.id === active.id);
      const overId = String(over.id);
      // Only reorder when dropping onto another group (not a field tile).
      const newIndex = current.findIndex((g) => g.id === overId);
      if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) return;
      const next = arrayMove(current, oldIndex, newIndex);
      orderedGroupsRef.current = next;
      setOrderedGroups(next);
      void persistGroupOrder(next);
      return;
    }

    if (kind === "field") {
      const map = fieldsByGroupRef.current;
      const container = findContainer(active.id, map);
      if (!container) {
        fieldsLayoutDirtyRef.current = false;
        return;
      }

      const list = map[container] ?? [];
      const oldIndex = list.findIndex((f) => f.id === active.id);
      const overContainer = findContainer(over.id, map) ?? container;
      const overIdStr = String(over.id);
      const droppedOnContainer = overIdStr.startsWith("container:");

      // Cross-group moves are applied in onDragOver. By drag end the field already sits
      // in the target group, so container === overContainer — we must still persist.
      if (fieldsLayoutDirtyRef.current) {
        fieldsLayoutDirtyRef.current = false;
        void persistFieldsByGroup(map);
        return;
      }

      if (container === overContainer && !droppedOnContainer) {
        const overList = map[overContainer] ?? [];
        const newIndex = overList.findIndex((f) => f.id === over.id);
        if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) return;
        const nextList = arrayMove(list, oldIndex, newIndex);
        const nextMap = { ...map, [container]: nextList };
        fieldsByGroupRef.current = nextMap;
        setFieldsByGroup(nextMap);
        void persistFieldsByGroup(nextMap);
        return;
      }

      // Drop onto empty group / container droppable without a prior onDragOver move.
      if (container !== overContainer || droppedOnContainer) {
        void persistFieldsByGroup(map);
      }
    }
  }

  function onDragCancel() {
    setActiveId(null);
    setActiveKind(null);
    fieldsLayoutDirtyRef.current = false;
  }

  const groupIds = orderedGroups.map((g) => g.id);

  const content = (
    <div className="space-y-3">
      {sectionKeys.map((key) => {
        if (key === UNGROUPED) {
          return (
            <FieldGroupCard
              key={key}
              containerId={key}
              title={orderedGroups.length === 0 ? "Fields" : "Other fields"}
              group={null}
              fields={fieldsByGroup[key] ?? []}
              collapsed={Boolean(collapsed[UNGROUPED])}
              schemaTools={schemaTools}
              schemaMenus={schemaMenus}
              selectedFieldId={selectedFieldId}
              mode={mode}
              sortableGroup={false}
              onToggle={() => setCollapsed((c) => ({ ...c, [UNGROUPED]: !c[UNGROUPED] }))}
              onSchemaAction={onSchemaAction}
              onDeleteGroup={deleteGroup}
              onFieldSelect={(field) => setSelectedFieldId(field.id)}
              renderFieldValue={renderFieldValue}
            />
          );
        }

        const group = orderedGroups.find((g) => g.id === key);
        if (!group) return null;

        if (schemaTools) {
          return (
            <SortableGroupCard
              key={key}
              group={group}
              fields={fieldsByGroup[key] ?? []}
              collapsed={Boolean(collapsed[group.id])}
              schemaTools={schemaTools}
              schemaMenus={schemaMenus}
              selectedFieldId={selectedFieldId}
              mode={mode}
              onToggle={() =>
                setCollapsed((c) => ({ ...c, [group.id]: !c[group.id] }))
              }
              onSchemaAction={onSchemaAction}
              onDeleteGroup={deleteGroup}
              onFieldSelect={(field) => setSelectedFieldId(field.id)}
              renderFieldValue={renderFieldValue}
            />
          );
        }

        return (
          <FieldGroupCard
            key={key}
            containerId={group.id}
            title={group.name}
            group={group}
            fields={fieldsByGroup[key] ?? []}
            collapsed={Boolean(collapsed[group.id])}
            schemaTools={false}
            schemaMenus={schemaMenus}
            selectedFieldId={selectedFieldId}
            mode={mode}
            sortableGroup={false}
            onToggle={() =>
              setCollapsed((c) => ({ ...c, [group.id]: !c[group.id] }))
            }
            onSchemaAction={onSchemaAction}
            onDeleteGroup={deleteGroup}
            onFieldSelect={(field) => setSelectedFieldId(field.id)}
            renderFieldValue={renderFieldValue}
          />
        );
      })}
    </div>
  );

  return (
    <div className="space-y-3">
      {schemaTools ? (
        <p className="text-xs text-muted-foreground">
          Drag the handle to reorder. Right-click a field or group for options.
          {savingLayout ? " Saving layout…" : null}
        </p>
      ) : schemaMenus ? (
        <p className="text-xs text-muted-foreground">
          Right-click a field or empty space in a group for options.
          {mode === "view" ? " Turn on Customize to drag and reorder." : null}
        </p>
      ) : savingLayout ? (
        <p className="text-xs text-muted-foreground">Saving layout…</p>
      ) : null}

      {schemaTools ? (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCorners}
          onDragStart={onDragStart}
          onDragOver={onDragOver}
          onDragEnd={onDragEnd}
          onDragCancel={onDragCancel}
        >
          <SortableContext items={groupIds} strategy={verticalListSortingStrategy}>
            {content}
          </SortableContext>
          <DragOverlay dropAnimation={null}>
            {activeField ? (
              <div className="rounded-lg border border-sky-300 bg-card p-3 shadow-lg ring-2 ring-sky-500/30">
                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                  {activeField.label}
                </p>
                <div className="mt-1 text-sm text-foreground">{renderFieldValue(activeField)}</div>
              </div>
            ) : null}
            {activeGroup ? (
              <div className="rounded-xl border border-sky-300 bg-card px-4 py-3 shadow-lg ring-2 ring-sky-500/30">
                <p className="text-sm font-semibold">{activeGroup.name}</p>
              </div>
            ) : null}
          </DragOverlay>
        </DndContext>
      ) : (
        content
      )}
    </div>
  );
}

function SortableGroupCard({
  group,
  fields,
  collapsed,
  schemaTools,
  schemaMenus,
  selectedFieldId,
  mode,
  onToggle,
  onSchemaAction,
  onDeleteGroup,
  onFieldSelect,
  renderFieldValue,
}: {
  group: Group;
  fields: DynField[];
  collapsed: boolean;
  schemaTools: boolean;
  schemaMenus: boolean;
  selectedFieldId: string | null;
  mode: "view" | "form";
  onToggle: () => void;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteGroup: (group: Group) => void;
  onFieldSelect: (field: DynField) => void;
  renderFieldValue: (field: DynField) => ReactNode;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: group.id,
    disabled: !schemaTools,
    data: { kind: "group" },
  });

  return (
    <div
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
        opacity: isDragging ? 0.35 : undefined,
      }}
    >
      <FieldGroupCard
        containerId={group.id}
        title={group.name}
        group={group}
        fields={fields}
        collapsed={collapsed}
        schemaTools={schemaTools}
        schemaMenus={schemaMenus}
        selectedFieldId={selectedFieldId}
        mode={mode}
        sortableGroup={schemaTools}
        dragHandleProps={{ attributes, listeners }}
        onToggle={onToggle}
        onSchemaAction={onSchemaAction}
        onDeleteGroup={onDeleteGroup}
        onFieldSelect={onFieldSelect}
        renderFieldValue={renderFieldValue}
      />
    </div>
  );
}

function FieldGroupCard({
  containerId,
  title,
  group,
  fields,
  collapsed,
  schemaTools,
  schemaMenus,
  selectedFieldId,
  mode,
  sortableGroup,
  dragHandleProps,
  onToggle,
  onSchemaAction,
  onDeleteGroup,
  onFieldSelect,
  renderFieldValue,
}: {
  containerId: string;
  title: string;
  group: Group | null;
  fields: DynField[];
  collapsed: boolean;
  schemaTools: boolean;
  schemaMenus: boolean;
  selectedFieldId: string | null;
  mode: "view" | "form";
  sortableGroup: boolean;
  dragHandleProps?: {
    attributes: ReturnType<typeof useSortable>["attributes"];
    listeners: ReturnType<typeof useSortable>["listeners"];
  };
  onToggle: () => void;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteGroup: (group: Group) => void;
  onFieldSelect: (field: DynField) => void;
  renderFieldValue: (field: DynField) => ReactNode;
}) {
  const { setNodeRef, isOver } = useDroppable({
    id: `container:${containerId}`,
    data: { kind: "container", groupId: containerId },
    disabled: !schemaTools,
  });

  const fieldIds = fields.map((f) => f.id);
  const useChrome = schemaTools || schemaMenus;
  const isPlainView = mode === "view" && !useChrome;

  const card = (
    <section
      className={cn(
        "overflow-hidden rounded-xl border border-border bg-card shadow-sm",
        isOver && schemaTools && "ring-2 ring-sky-500/40",
      )}
    >
      <header className="flex items-center gap-2 border-b border-border/80 bg-muted/30 px-3 py-2.5">
        {sortableGroup ? (
          <button
            type="button"
            className="cursor-grab touch-none text-muted-foreground hover:text-foreground active:cursor-grabbing"
            aria-label={`Move ${title}`}
            {...(dragHandleProps?.attributes ?? {})}
            {...(dragHandleProps?.listeners ?? {})}
          >
            <GripVertical className="size-4" />
          </button>
        ) : (
          <span className="inline-flex w-4" />
        )}
        <button
          type="button"
          className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
          onClick={onToggle}
          aria-expanded={!collapsed}
          aria-label={collapsed ? `Expand ${title}` : `Collapse ${title}`}
        >
          <ChevronDown className={cn("size-4 transition-transform", collapsed && "-rotate-90")} />
        </button>
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-sm font-semibold tracking-tight text-foreground">{title}</h3>
        </div>
        <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium text-muted-foreground">
          {fields.length}
        </span>
        {schemaMenus && group ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={`Edit group ${title}`}
            onClick={() => onSchemaAction({ kind: "edit-group", group })}
          >
            <Pencil className="size-3.5" />
          </Button>
        ) : null}
        {schemaMenus ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={`Add field to ${title}`}
            onClick={() => onSchemaAction({ kind: "add-field", groupId: group?.id ?? null })}
          >
            <Plus className="size-3.5" />
          </Button>
        ) : null}
      </header>

      {!collapsed ? (
        <div
          ref={setNodeRef}
          className={cn("min-h-16 p-3", isOver && schemaTools && "bg-sky-50/50 dark:bg-sky-950/20")}
        >
          {fields.length === 0 ? (
            <p className="py-4 text-center text-sm text-muted-foreground">
              {schemaMenus ? "Drop fields here or right-click to manage group." : "No fields in this group."}
            </p>
          ) : schemaTools ? (
            <SortableContext items={fieldIds} strategy={rectSortingStrategy}>
              <div className="grid grid-cols-12 gap-2">
                {fields.map((field) => (
                  <SortableFieldTile
                    key={field.id}
                    field={field}
                    group={group}
                    groupId={containerId}
                    schemaTools
                    schemaMenus={schemaMenus}
                    selected={selectedFieldId === field.id}
                    isView={false}
                    onSchemaAction={onSchemaAction}
                    onDeleteGroup={onDeleteGroup}
                    onSelect={() => onFieldSelect(field)}
                    renderFieldValue={renderFieldValue}
                  />
                ))}
              </div>
            </SortableContext>
          ) : (
            <div className="grid grid-cols-12 gap-2">
              {fields.map((field) =>
                useChrome ? (
                  <FieldTile
                    key={field.id}
                    field={field}
                    group={group}
                    schemaTools={false}
                    schemaMenus={schemaMenus}
                    selected={selectedFieldId === field.id}
                    isView={isPlainView}
                    onSchemaAction={onSchemaAction}
                    onDeleteGroup={onDeleteGroup}
                    onSelect={() => onFieldSelect(field)}
                    renderFieldValue={renderFieldValue}
                  />
                ) : (
                  <div
                    key={field.id}
                    style={dynColumnSpanStyle(field.column_span)}
                    className={cn(isPlainView ? "rounded-md px-1 py-2" : "rounded-lg border border-border/80 bg-background p-3")}
                  >
                    <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                      {field.label}
                    </span>
                    <div className="mt-0.5 text-sm text-foreground">{renderFieldValue(field)}</div>
                  </div>
                ),
              )}
            </div>
          )}
        </div>
      ) : null}
    </section>
  );

  if (!schemaMenus) return card;

  return (
    <ContextMenu>
      <ContextMenuTrigger className="block">{card}</ContextMenuTrigger>
      <ContextMenuContent>
        <SchemaMenuItems
          group={group}
          onSchemaAction={onSchemaAction}
          onDeleteGroup={onDeleteGroup}
        />
      </ContextMenuContent>
    </ContextMenu>
  );
}

function SortableFieldTile({
  field,
  group,
  groupId,
  schemaTools,
  schemaMenus,
  selected,
  isView,
  onSchemaAction,
  onDeleteGroup,
  onSelect,
  renderFieldValue,
}: {
  field: DynField;
  group: Group | null;
  groupId: string;
  schemaTools: boolean;
  schemaMenus: boolean;
  selected: boolean;
  isView: boolean;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteGroup: (group: Group) => void;
  onSelect: () => void;
  renderFieldValue: (field: DynField) => ReactNode;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: field.id,
    disabled: !schemaTools,
    data: { kind: "field", groupId },
  });

  return (
    <div
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
        opacity: isDragging ? 0.25 : undefined,
        ...dynColumnSpanStyle(field.column_span),
      }}
    >
      <FieldTile
        field={field}
        group={group}
        schemaTools={schemaTools}
        schemaMenus={schemaMenus}
        selected={selected}
        isView={isView}
        onSchemaAction={onSchemaAction}
        onDeleteGroup={onDeleteGroup}
        onSelect={onSelect}
        renderFieldValue={renderFieldValue}
        dragHandleProps={schemaTools ? { attributes, listeners } : undefined}
      />
    </div>
  );
}

function FieldTile({
  field,
  group,
  schemaTools,
  schemaMenus,
  selected,
  isView,
  onSchemaAction,
  onDeleteGroup,
  onSelect,
  renderFieldValue,
  dragHandleProps,
}: {
  field: DynField;
  group: Group | null;
  schemaTools: boolean;
  schemaMenus: boolean;
  selected: boolean;
  isView: boolean;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteGroup: (group: Group) => void;
  onSelect: () => void;
  renderFieldValue: (field: DynField) => ReactNode;
  dragHandleProps?: {
    attributes: ReturnType<typeof useSortable>["attributes"];
    listeners: ReturnType<typeof useSortable>["listeners"];
  };
}) {
  const tileClassName = cn(
    "group relative rounded-lg bg-background p-3 transition-colors",
    isView && !schemaMenus ? "border-0 px-1 py-2" : "border border-border/80",
    schemaMenus && "cursor-context-menu hover:border-sky-300",
    selected &&
      "border-l-[3px] border-l-sky-500 border-sky-200 bg-sky-50/40 dark:border-sky-800 dark:bg-sky-950/20",
  );

  const handleClick = (e: MouseEvent) => {
    if (!schemaMenus) return;
    if (
      (e.target as HTMLElement).closest(
        "input,textarea,select,button,a,[role='combobox'],[contenteditable='true']",
      )
    ) {
      return;
    }
    onSelect();
  };

  const inner = (
    <>
      <div className="mb-1 flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
            {field.label}
            {field.is_required && !isView ? " *" : ""}
          </span>
          <div className="mt-0.5 text-sm text-foreground">{renderFieldValue(field)}</div>
        </div>
        {schemaTools && dragHandleProps ? (
          <button
            type="button"
            className={cn(
              "mt-0.5 shrink-0 cursor-grab touch-none rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground active:cursor-grabbing",
              selected ? "opacity-100" : "opacity-40 hover:opacity-100",
            )}
            aria-label={`Move ${field.label}`}
            onClick={(e) => e.stopPropagation()}
            {...dragHandleProps.attributes}
            {...dragHandleProps.listeners}
          >
            <GripVertical className="size-3.5" />
          </button>
        ) : schemaMenus ? (
          <span
            className={cn(
              "mt-0.5 shrink-0 text-muted-foreground",
              selected ? "opacity-70" : "opacity-0 group-hover:opacity-50",
            )}
            aria-hidden
          >
            <GripVertical className="size-3.5" />
          </span>
        ) : null}
      </div>
    </>
  );

  if (!schemaMenus) {
    return (
      <div data-dyn-field-tile style={dynColumnSpanStyle(field.column_span)} className={tileClassName}>
        {inner}
      </div>
    );
  }

  return (
    <ContextMenu
      onOpenChange={(open) => {
        if (open) onSelect();
      }}
    >
      <ContextMenuTrigger
        data-dyn-field-tile
        style={dynColumnSpanStyle(field.column_span)}
        className={tileClassName}
        onClick={handleClick}
      >
        {inner}
      </ContextMenuTrigger>
      <ContextMenuContent>
        <SchemaMenuItems
          field={field}
          group={group}
          onSchemaAction={onSchemaAction}
          onDeleteGroup={onDeleteGroup}
        />
      </ContextMenuContent>
    </ContextMenu>
  );
}
