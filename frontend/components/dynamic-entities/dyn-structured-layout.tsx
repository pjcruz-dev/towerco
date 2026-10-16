"use client";

import { useCallback, useEffect, useMemo, useRef, useState, type MouseEvent, type ReactNode } from "react";
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
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ChevronDown, GripVertical, Layers, Pencil, Plus, SlidersHorizontal, SquarePen, Trash2 } from "lucide-react";

import type { DynSchemaMode } from "@/components/dynamic-entities/dyn-schema-sheets";
import {
  RoleFieldAccessBadge,
  RoleFieldAccessMenuItems,
  RoleFieldAccessProvider,
  ViewingAsMenuItems,
  ViewingAsStatus,
  useRoleFieldAccessContext,
} from "@/components/dynamic-entities/dyn-role-field-access";
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
  deleteDynField,
  deleteDynFieldGroup,
  type DynEntityDetail,
  type DynField,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  useFieldColumnSpan,
  useWidthAdjust,
  WidthAdjustProvider,
  WidthEdgeHandle,
} from "@/components/dynamic-entities/dyn-width-adjust";
import { getErrorMessage } from "@/lib/api/error";
import { clampSpan, dynColumnSpanStyle } from "@/lib/dynamic-entities/form-arrange-rows";
import { notify } from "@/lib/ui/notify";
import { fieldEditableOnForm } from "@/lib/rbac/field-surface-access";

type Group = DynEntityDetail["field_groups"][number];

type Props = {
  groups: Group[];
  fields: DynField[];
  entitySlug?: string;
  canManageSchema: boolean;
  layoutEditing?: boolean;
  /** Record edit and create fill values. Width tools stay on Customize. */
  allowWidthAdjust?: boolean;
  mode?: "view" | "form";
  includeUngrouped?: boolean;
  renderFieldValue: (field: DynField) => ReactNode;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onLayoutChanged: () => Promise<void> | void;
  /** Field names that failed the latest required check. The card picks up a destructive ring. */
  invalidFieldNames?: ReadonlySet<string>;
};

const UNGROUPED = "__ungrouped__";

function fieldPaintKey(field: DynField | undefined): string {
  if (!field) return "";
  return [
    field.id,
    field.column_span,
    field.is_required,
    field.is_key,
    field.label,
    field.type,
    field.field_order,
    field.form_group_id,
    field.view_group_id,
    field.placeholder,
    field.show_in_table,
    field.is_filterable,
  ].join("|");
}

function groupKeyForField(field: DynField, mode: "view" | "form"): string {
  // Prefer form grouping so View shows the same whole groups as Arrange Form.
  const id =
    mode === "form"
      ? (field.form_group_id ?? field.view_group_id)
      : (field.form_group_id ?? field.view_group_id);
  return id ?? UNGROUPED;
}

export function DynStructuredLayout({
  groups,
  fields,
  entitySlug,
  canManageSchema,
  layoutEditing,
  allowWidthAdjust = true,
  mode = "view",
  includeUngrouped = true,
  renderFieldValue,
  onSchemaAction,
  onLayoutChanged,
  invalidFieldNames,
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
  const [selectedGroupId, setSelectedGroupId] = useState<string | null>(null);
  const [savingLayout, setSavingLayout] = useState(false);
  const [adjustingWidths, setAdjustingWidths] = useState(false);
  const [widthSpans, setWidthSpans] = useState<Record<string, number>>({});
  const [groupSpans, setGroupSpans] = useState<Record<string, number>>({});
  const [savingWidths, setSavingWidths] = useState(false);
  const setWidthSpan = useCallback((fieldId: string, span: number) => {
    setWidthSpans((current) => ({ ...current, [fieldId]: clampSpan(span) }));
  }, []);
  const setGroupSpan = useCallback((groupId: string, span: number) => {
    setGroupSpans((current) => ({ ...current, [groupId]: clampSpan(span) }));
  }, []);
  const widthSpansRef = useRef(widthSpans);
  const groupSpansRef = useRef(groupSpans);
  const fieldsRef = useRef(fields);
  widthSpansRef.current = widthSpans;
  groupSpansRef.current = groupSpans;
  fieldsRef.current = fields;
  const widthsDirty = fields.some((field) => {
    const next = widthSpans[field.id];
    return next !== undefined && next !== clampSpan(field.column_span ?? 12);
  });
  const groupsDirty = orderedGroups.some((group) => {
    const next = groupSpans[group.id];
    return next !== undefined && next !== clampSpan(group.column_span ?? 12);
  });

  function displayGroupSpan(group: Group): number {
    return groupSpans[group.id] ?? clampSpan(group.column_span ?? 12);
  }

  async function saveWidths() {
    const spans = widthSpansRef.current;
    const nextGroupSpans = groupSpansRef.current;
    const currentFields = fieldsRef.current;
    const changed = currentFields.filter((field) => {
      const next = spans[field.id];
      return next !== undefined && next !== clampSpan(field.column_span ?? 12);
    });
    const changedGroups = orderedGroupsRef.current.filter((group) => {
      const next = nextGroupSpans[group.id];
      return next !== undefined && next !== clampSpan(group.column_span ?? 12);
    });
    if (changed.length === 0 && changedGroups.length === 0) {
      setAdjustingWidths(false);
      return;
    }
    setSavingWidths(true);
    try {
      await Promise.all([
        ...changed.map((field) => updateDynField(field.id, { column_span: spans[field.id] })),
        ...changedGroups.map((group) =>
          updateDynFieldGroup(group.id, { column_span: nextGroupSpans[group.id] }),
        ),
      ]);
      setAdjustingWidths(false);
      await onLayoutChanged();
      setWidthSpans({});
      setGroupSpans({});
      notify.success("Form layout saved");
    } catch (error) {
      notify.error("Could not save form layout", getErrorMessage(error));
    } finally {
      setSavingWidths(false);
    }
  }

  const cancelWidths = useCallback(() => {
    setWidthSpans({});
    setGroupSpans({});
    setAdjustingWidths(false);
  }, []);

  function startOrFinishWidths() {
    if (adjustingWidths) {
      void saveWidths();
      return;
    }
    setAdjustingWidths(true);
  }
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
          if (
            a.length !== b.length ||
            a.some((field, i) => fieldPaintKey(field) !== fieldPaintKey(b[i]))
          ) {
            same = false;
            break;
          }
        }
        if (same) return prev;
      }
      return next;
    });
  }, [fields, groups, mode]);

  async function deleteField(field: DynField) {
    if (field.is_system_field || ["actions", "workflows", "print", "id"].includes(field.name)) {
      notify.error("System fields stay on the record", "You can rename or move them, not delete them.");
      return;
    }
    if (
      !window.confirm(
        `Delete field “${field.label}”? It leaves the form. Values already stored stay on existing records.`,
      )
    ) {
      return;
    }
    setSavingLayout(true);
    try {
      await deleteDynField(field.id);
      await onLayoutChanged();
      notify.success("Field deleted");
    } catch (error) {
      notify.error("Could not delete field", getErrorMessage(error));
    } finally {
      setSavingLayout(false);
    }
  }

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
      let overId = String(over.id);
      if (overId.startsWith("container:")) overId = overId.slice("container:".length);
      let newIndex = current.findIndex((g) => g.id === overId);
      if (newIndex < 0) {
        const container = findContainer(over.id);
        if (container && container !== UNGROUPED) {
          newIndex = current.findIndex((g) => g.id === container);
        }
      }
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
    <div className="grid grid-cols-12 items-start gap-3">
      {sectionKeys.map((key) => {
        if (key === UNGROUPED) {
          return (
            <GroupGridItem key={key} span={12}>
              <FieldGroupCard
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
                onDeleteField={deleteField}
                onDeleteGroup={deleteGroup}
                onFieldSelect={selectField}
                onSelectGroup={selectGroup}
                groupSelected={false}
                onAdjustWidths={allowWidthAdjust ? startOrFinishWidths : undefined}
                widthAdjusting={adjustingWidths}
                renderFieldValue={renderFieldValue}
                invalidFieldNames={invalidFieldNames}
              />
            </GroupGridItem>
          );
        }

        const group = orderedGroups.find((g) => g.id === key);
        if (!group) return null;
        const columnSpan = displayGroupSpan(group);

        if (schemaTools) {
          return (
            <SortableGroupCard
              key={key}
              group={group}
              columnSpan={columnSpan}
              onColumnSpan={(span) => setGroupSpan(group.id, span)}
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
              onDeleteField={deleteField}
              onDeleteGroup={deleteGroup}
              onFieldSelect={selectField}
              onSelectGroup={selectGroup}
              groupSelected={selectedGroupId === group.id && selectedFieldId === null}
              onAdjustWidths={allowWidthAdjust ? startOrFinishWidths : undefined}
              widthAdjusting={adjustingWidths}
              renderFieldValue={renderFieldValue}
              invalidFieldNames={invalidFieldNames}
            />
          );
        }

        return (
          <GroupGridItem
            key={key}
            span={columnSpan}
            widthLabel={adjustingWidths ? group.name : undefined}
            widthSpan={adjustingWidths ? columnSpan : undefined}
            onWidthSpan={adjustingWidths ? (span) => setGroupSpan(group.id, span) : undefined}
          >
            <FieldGroupCard
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
              onDeleteField={deleteField}
              onDeleteGroup={deleteGroup}
              onFieldSelect={selectField}
              onSelectGroup={selectGroup}
              groupSelected={selectedGroupId === group.id && selectedFieldId === null}
              onAdjustWidths={allowWidthAdjust ? startOrFinishWidths : undefined}
              widthAdjusting={adjustingWidths}
              renderFieldValue={renderFieldValue}
              invalidFieldNames={invalidFieldNames}
            />
          </GroupGridItem>
        );
      })}
    </div>
  );

  const widthActive = allowWidthAdjust && adjustingWidths;

  function selectField(field: DynField) {
    setSelectedFieldId(field.id);
    setSelectedGroupId(field.form_group_id ?? field.view_group_id ?? null);
  }

  function selectGroup(group: Group | null) {
    setSelectedFieldId(null);
    setSelectedGroupId(group?.id ?? null);
  }

  const layout = (
    <WidthAdjustProvider
      spans={widthSpans}
      active={widthActive}
      dirty={widthsDirty || groupsDirty}
      saving={savingWidths}
      setSpan={setWidthSpan}
      discard={cancelWidths}
    >
    <div className="space-y-3">
      {schemaMenus ? <ViewingAsStatus /> : null}
      {widthActive ? (
        <p className="text-xs text-muted-foreground">
          Drag the handle on the right of a group or field. Arrow keys nudge by one column. Right-click and choose
          Done adjusting to save.
          {savingWidths ? " Saving…" : null}
        </p>
      ) : schemaTools ? (
        <p className="text-xs text-muted-foreground">
          Drag a group handle to move the whole group. Drag a field handle to reorder fields inside it.
          {savingLayout ? " Saving…" : null}
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
          <SortableContext items={groupIds} strategy={rectSortingStrategy}>
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
    </WidthAdjustProvider>
  );

  if (!entitySlug || !schemaMenus) {
    return layout;
  }

  return <RoleFieldAccessProvider entitySlug={entitySlug}>{layout}</RoleFieldAccessProvider>;
}

function SchemaMenuItems({
  field,
  group,
  widthAdjusting,
  onSchemaAction,
  onDeleteField,
  onDeleteGroup,
  onAdjustWidths,
}: {
  field?: DynField | null;
  group: Group | null;
  widthAdjusting?: boolean;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteField?: (field: DynField) => void;
  onDeleteGroup?: (group: Group) => void;
  onAdjustWidths?: () => void;
}) {
  const widths = useWidthAdjust();
  const canPreviewRoles = Boolean(useRoleFieldAccessContext()?.canManageRoles);
  return (
    <>
      <ContextMenuLabel>Field</ContextMenuLabel>
      <ContextMenuItem
        className="text-emerald-700 dark:text-emerald-400"
        onClick={() => onSchemaAction({ kind: "add-field", groupId: group?.id ?? null })}
      >
        <Plus className="size-3.5" />
        Add field
      </ContextMenuItem>
      {field ? (
        <>
          <ContextMenuItem onClick={() => onSchemaAction({ kind: "edit-field", field })}>
            <SquarePen className="size-3.5 text-muted-foreground" />
            Edit field
          </ContextMenuItem>
          <ContextMenuItem destructive onClick={() => onDeleteField?.(field)}>
            <Trash2 className="size-3.5" />
            Delete field
          </ContextMenuItem>
        </>
      ) : null}
      <ContextMenuSeparator />
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
            Delete group
          </ContextMenuItem>
        </>
      ) : null}
      {onAdjustWidths ? (
        <>
          <ContextMenuSeparator />
          <ContextMenuItem onClick={onAdjustWidths} disabled={widths?.saving}>
            <SlidersHorizontal className="size-3.5" />
            {widths?.saving ? "Saving…" : widthAdjusting ? "Done adjusting" : "Adjust widths"}
          </ContextMenuItem>
          {widthAdjusting && widths?.dirty ? (
            <ContextMenuItem onClick={() => widths?.discard()} disabled={widths?.saving}>
              Discard widths
            </ContextMenuItem>
          ) : null}
        </>
      ) : null}
      {canPreviewRoles ? (
        <>
          <ContextMenuSeparator />
          {field ? (
            <RoleFieldAccessMenuItems fieldName={field.name} fieldLabel={field.label} />
          ) : (
            <ViewingAsMenuItems />
          )}
        </>
      ) : null}
    </>
  );
}

function GroupGridItem({
  span,
  widthLabel,
  widthSpan,
  onWidthSpan,
  setNodeRef,
  dragStyle,
  children,
}: {
  span: number;
  widthLabel?: string;
  widthSpan?: number;
  onWidthSpan?: (span: number) => void;
  setNodeRef?: (node: HTMLElement | null) => void;
  dragStyle?: { transform?: string; transition?: string; opacity?: number };
  children: ReactNode;
}) {
  return (
    <div
      ref={setNodeRef}
      data-dyn-group-tile
      className="relative min-w-0"
      style={{
        ...dynColumnSpanStyle(span),
        transform: dragStyle?.transform,
        transition: dragStyle?.transition,
        opacity: dragStyle?.opacity,
      }}
    >
      {children}
      {widthLabel && widthSpan !== undefined && onWidthSpan ? (
        <WidthEdgeHandle label={widthLabel} span={widthSpan} onSpan={onWidthSpan} />
      ) : null}
    </div>
  );
}

function SortableGroupCard({
  group,
  columnSpan,
  onColumnSpan,
  fields,
  collapsed,
  schemaTools,
  schemaMenus,
  selectedFieldId,
  mode,
  onToggle,
  onSchemaAction,
  onDeleteField,
  onDeleteGroup,
  onFieldSelect,
  onSelectGroup,
  groupSelected,
  onAdjustWidths,
  widthAdjusting,
  renderFieldValue,
  invalidFieldNames,
}: {
  group: Group;
  columnSpan: number;
  onColumnSpan: (span: number) => void;
  fields: DynField[];
  collapsed: boolean;
  schemaTools: boolean;
  schemaMenus: boolean;
  selectedFieldId: string | null;
  mode: "view" | "form";
  onToggle: () => void;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteField: (field: DynField) => void;
  onDeleteGroup: (group: Group) => void;
  onFieldSelect: (field: DynField) => void;
  onSelectGroup: (group: Group | null) => void;
  groupSelected: boolean;
  onAdjustWidths?: () => void;
  widthAdjusting?: boolean;
  renderFieldValue: (field: DynField) => ReactNode;
  invalidFieldNames?: ReadonlySet<string>;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: group.id,
    disabled: !schemaTools,
    data: { kind: "group" },
  });

  return (
    <GroupGridItem
      span={columnSpan}
      setNodeRef={setNodeRef}
      dragStyle={{
        transform: CSS.Translate.toString(transform) ?? undefined,
        transition,
        opacity: isDragging ? 0.35 : undefined,
      }}
      widthLabel={widthAdjusting ? group.name : undefined}
      widthSpan={widthAdjusting ? columnSpan : undefined}
      onWidthSpan={widthAdjusting ? onColumnSpan : undefined}
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
          onDeleteField={onDeleteField}
          onDeleteGroup={onDeleteGroup}
        onFieldSelect={onFieldSelect}
        onSelectGroup={onSelectGroup}
        groupSelected={groupSelected}
        onAdjustWidths={onAdjustWidths}
        widthAdjusting={widthAdjusting}
        renderFieldValue={renderFieldValue}
        invalidFieldNames={invalidFieldNames}
      />
    </GroupGridItem>
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
  onDeleteField,
  onDeleteGroup,
  onFieldSelect,
  onSelectGroup,
  groupSelected,
  onAdjustWidths,
  widthAdjusting,
  renderFieldValue,
  invalidFieldNames,
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
  onDeleteField: (field: DynField) => void;
  onDeleteGroup: (group: Group) => void;
  onFieldSelect: (field: DynField) => void;
  onSelectGroup: (group: Group | null) => void;
  groupSelected: boolean;
  onAdjustWidths?: () => void;
  widthAdjusting?: boolean;
  renderFieldValue: (field: DynField) => ReactNode;
  invalidFieldNames?: ReadonlySet<string>;
}) {
  const { setNodeRef, isOver } = useDroppable({
    id: `container:${containerId}`,
    data: { kind: "container", groupId: containerId },
    disabled: !schemaTools,
  });

  const fieldIds = fields.map((f) => f.id);
  const useChrome = schemaTools || schemaMenus;
  const isPlainView = mode === "view" && !useChrome;
  const revealInvalid = fields.some((field) => invalidFieldNames?.has(field.name));
  const bodyCollapsed = collapsed && !revealInvalid;

  const card = (
    <section
      className={cn(
        "overflow-hidden rounded-xl border border-border bg-card shadow-sm",
        isOver && schemaTools && "ring-2 ring-sky-500/40",
        groupSelected && "ring-2 ring-primary/40",
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
        {schemaMenus ? (
          <button
            type="button"
            className="min-w-0 flex-1 truncate text-left"
            aria-pressed={groupSelected}
            onClick={() => onSelectGroup(group)}
          >
            <h3 className="truncate text-sm font-semibold tracking-tight text-foreground">{title}</h3>
          </button>
        ) : (
          <div className="min-w-0 flex-1">
            <h3 className="truncate text-sm font-semibold tracking-tight text-foreground">{title}</h3>
          </div>
        )}
        <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium text-muted-foreground">
          {fields.length}
        </span>
      </header>

      {!bodyCollapsed ? (
        <div
          ref={setNodeRef}
          className={cn("min-h-16 p-3", isOver && schemaTools && "bg-sky-50/50 dark:bg-sky-950/20")}
        >
          {fields.length === 0 ? (
            <p className="py-4 text-center text-sm text-muted-foreground">
              {schemaMenus ? "Drop fields here or right-click to manage the group." : "No fields in this group."}
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
                    invalid={Boolean(invalidFieldNames?.has(field.name))}
                    isView={false}
                    onSchemaAction={onSchemaAction}
          onDeleteField={onDeleteField}
          onDeleteGroup={onDeleteGroup}
                    onAdjustWidths={onAdjustWidths}
                    widthAdjusting={widthAdjusting}
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
                    invalid={Boolean(invalidFieldNames?.has(field.name))}
                    isView={mode === "view"}
                    onSchemaAction={onSchemaAction}
          onDeleteField={onDeleteField}
          onDeleteGroup={onDeleteGroup}
                    onAdjustWidths={onAdjustWidths}
                    widthAdjusting={widthAdjusting}
                    onSelect={() => onFieldSelect(field)}
                    renderFieldValue={renderFieldValue}
                  />
                ) : (
                  <div
                    key={field.id}
                    style={dynColumnSpanStyle(field.column_span)}
                    className={cn(
                      isPlainView ? "rounded-md px-1 py-2" : "rounded-lg border border-border/80 bg-background p-3",
                      invalidFieldNames?.has(field.name) && "scroll-mt-24 border-destructive ring-2 ring-destructive/30",
                    )}
                    {...(invalidFieldNames?.has(field.name) ? { "data-dyn-invalid": "" } : {})}
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
          widthAdjusting={widthAdjusting}
          onSchemaAction={onSchemaAction}
          onDeleteField={onDeleteField}
          onDeleteGroup={onDeleteGroup}
          onAdjustWidths={onAdjustWidths}
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
  invalid = false,
  isView,
  onSchemaAction,
  onDeleteField,
  onDeleteGroup,
  onAdjustWidths,
  widthAdjusting,
  onSelect,
  renderFieldValue,
}: {
  field: DynField;
  group: Group | null;
  groupId: string;
  schemaTools: boolean;
  schemaMenus: boolean;
  selected: boolean;
  invalid?: boolean;
  isView: boolean;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteField: (field: DynField) => void;
  onDeleteGroup: (group: Group) => void;
  onAdjustWidths?: () => void;
  widthAdjusting?: boolean;
  onSelect: () => void;
  renderFieldValue: (field: DynField) => ReactNode;
}) {
  const span = useFieldColumnSpan(field);
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
        ...dynColumnSpanStyle(span),
      }}
    >
      <FieldTile
        field={field}
        group={group}
        schemaTools={schemaTools}
        schemaMenus={schemaMenus}
        selected={selected}
        invalid={invalid}
        isView={isView}
        onSchemaAction={onSchemaAction}
          onDeleteField={onDeleteField}
          onDeleteGroup={onDeleteGroup}
        onAdjustWidths={onAdjustWidths}
        widthAdjusting={widthAdjusting}
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
  invalid = false,
  isView,
  onSchemaAction,
  onDeleteField,
  onDeleteGroup,
  onAdjustWidths,
  widthAdjusting,
  onSelect,
  renderFieldValue,
  dragHandleProps,
}: {
  field: DynField;
  group: Group | null;
  schemaTools: boolean;
  schemaMenus: boolean;
  selected: boolean;
  invalid?: boolean;
  isView: boolean;
  onSchemaAction: (mode: DynSchemaMode) => void;
  onDeleteField: (field: DynField) => void;
  onDeleteGroup: (group: Group) => void;
  onAdjustWidths?: () => void;
  widthAdjusting?: boolean;
  onSelect: () => void;
  renderFieldValue: (field: DynField) => ReactNode;
  dragHandleProps?: {
    attributes: ReturnType<typeof useSortable>["attributes"];
    listeners: ReturnType<typeof useSortable>["listeners"];
  };
}) {
  const access = useRoleFieldAccessContext();
  const widths = useWidthAdjust();
  const span = useFieldColumnSpan(field);
  const previewLevel = access?.selected ? access.levelFor(field.name) : null;
  const tileClassName = cn(
    "group relative rounded-lg p-3 transition-colors bg-card",
    isView && !schemaMenus ? "border-0 px-1 py-2" : "border border-border/80",
    schemaMenus && "cursor-context-menu",
    invalid && "scroll-mt-24 border-destructive ring-2 ring-destructive/30",
    previewLevel === "hide" && "border-dashed opacity-60",
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
            <RoleFieldAccessBadge fieldName={field.name} />
          </span>
          <div
            className={cn(
              "mt-0.5 text-sm text-foreground",
              previewLevel && !fieldEditableOnForm(previewLevel) && "pointer-events-none",
            )}
          >
            {renderFieldValue(field)}
          </div>
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

  const body = (
    <>
      {inner}
      {widths?.active ? (
        <WidthEdgeHandle label={field.label} span={span} onSpan={(next) => widths.setSpan(field.id, next)} />
      ) : null}
    </>
  );

  // The grid child has to carry the column span. The context-menu root sits between
  // the grid and the card, so a span on the card alone collapses every field to 1 column.
  const framed = schemaMenus ? (
    <ContextMenu
      onOpenChange={(open: boolean) => {
        if (open) onSelect();
      }}
    >
      <ContextMenuTrigger
        data-dyn-field-tile
        {...(invalid ? { "data-dyn-invalid": "" } : {})}
        className={cn("block h-full", tileClassName)}
        onClick={handleClick}
      >
        {body}
      </ContextMenuTrigger>
      <ContextMenuContent>
        <SchemaMenuItems
          field={field}
          group={group}
          widthAdjusting={widthAdjusting}
          onSchemaAction={onSchemaAction}
          onDeleteField={onDeleteField}
          onDeleteGroup={onDeleteGroup}
          onAdjustWidths={onAdjustWidths}
        />
      </ContextMenuContent>
    </ContextMenu>
  ) : (
    <div
      data-dyn-field-tile
      {...(invalid ? { "data-dyn-invalid": "" } : {})}
      className={tileClassName}
      onClick={handleClick}
    >
      {body}
    </div>
  );

  return (
    <div style={dynColumnSpanStyle(span)} className="min-w-0">
      {framed}
    </div>
  );
}
