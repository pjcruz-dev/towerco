"use client";

import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  rectSortingStrategy,
  sortableKeyboardCoordinates,
  useSortable,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Check, GripVertical, Pencil, Plus, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import {
  DOC_EXTRACT_FIELD_TYPES,
  DOC_EXTRACT_TABLE_COLUMN_TYPES,
  emptyTableColumn,
  formatDocExtractFieldTypeShort,
  slugifyDocExtractKey,
} from "@/modules/doc-extract/field-types";
import type {
  DocExtractField,
  DocExtractFieldType,
  DocExtractTableColumn,
} from "@/modules/doc-extract/types";
import { useNotificationStore } from "@/stores/notification-store";

function emptyField(): DocExtractField {
  return {
    key: "",
    label: "",
    type: "text",
    description: "",
    hint: "",
    keyManual: false,
    columns: [],
  };
}

function cloneFields(fields: DocExtractField[]): DocExtractField[] {
  return fields.map((field) => ({
    ...field,
    columns: field.columns?.map((column) => ({ ...column })) ?? [],
  }));
}

function TypeBadge({ type }: { type: DocExtractFieldType }) {
  return (
    <span className="inline-flex items-center rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
      {formatDocExtractFieldTypeShort(type)}
    </span>
  );
}

function SortableColumnCard({
  id,
  disabled,
  children,
}: {
  id: string;
  disabled?: boolean;
  children: React.ReactNode;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled,
  });

  return (
    <div
      ref={setNodeRef}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
      }}
      className={cn("relative", isDragging && "z-20 opacity-90")}
    >
      {!disabled ? (
        <button
          type="button"
          className="absolute left-2 top-2 z-10 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
          title="Drag to reorder"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="size-4" />
        </button>
      ) : null}
      {children}
    </div>
  );
}

export function DocExtractColumnsDefinitionEditor({
  fields,
  onChange,
  disabled = false,
  readOnly = false,
}: {
  fields: DocExtractField[];
  onChange: (fields: DocExtractField[]) => void;
  disabled?: boolean;
  readOnly?: boolean;
}) {
  const notify = useNotificationStore((state) => state.push);
  const [localFields, setLocalFields] = useState(() => cloneFields(fields));
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  const [draft, setDraft] = useState<DocExtractField>(emptyField());

  useEffect(() => {
    if (editingIndex !== null) return;
    setLocalFields(cloneFields(fields));
  }, [fields, editingIndex]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const sortableIds = useMemo(
    () => localFields.map((field, index) => field.key || `col-${index}`),
    [localFields],
  );

  const emit = (next: DocExtractField[]) => {
    setLocalFields(next);
    onChange(cloneFields(next));
  };

  const patchDraft = (patch: Partial<DocExtractField>) => {
    setDraft((current) => {
      const next = { ...current, ...patch };
      if (patch.label !== undefined && !next.keyManual) {
        next.key = slugifyDocExtractKey(patch.label);
      }
      if (patch.type === "table" && (!next.columns || next.columns.length === 0)) {
        next.columns = [emptyTableColumn()];
      }
      return next;
    });
  };

  const patchTableColumn = (columnIndex: number, patch: Partial<DocExtractTableColumn>) => {
    setDraft((current) => {
      const columns = [...(current.columns ?? [])];
      const existing = columns[columnIndex] ?? emptyTableColumn();
      const nextColumn = { ...existing, ...patch };
      if (patch.label !== undefined) {
        nextColumn.key = slugifyDocExtractKey(patch.label);
      }
      columns[columnIndex] = nextColumn;
      return { ...current, columns };
    });
  };

  const commitColumn = () => {
    if (editingIndex === null) return;
    const label = draft.label.trim();
    if (!label) {
      notify({ level: "error", title: "Column name required" });
      return;
    }
    if (draft.type === "table" && (draft.columns ?? []).filter((column) => column.label.trim()).length === 0) {
      notify({ level: "error", title: "Add at least one table column" });
      return;
    }
    const nextField: DocExtractField = {
      ...draft,
      label,
      key: draft.keyManual ? draft.key : slugifyDocExtractKey(label),
      description: draft.description ?? "",
      columns:
        draft.type === "table"
          ? (draft.columns ?? [])
              .filter((column) => column.label.trim() !== "")
              .map((column) => ({
                ...column,
                key: column.key || slugifyDocExtractKey(column.label),
              }))
          : [],
    };
    const next = localFields.map((field, index) => (index === editingIndex ? nextField : field));
    setEditingIndex(null);
    setDraft(emptyField());
    emit(next);
  };

  const cancelColumn = () => {
    if (editingIndex === null) return;
    const existing = localFields[editingIndex];
    const isBlankNew = !existing?.label.trim();
    const next = isBlankNew ? localFields.filter((_, index) => index !== editingIndex) : localFields;
    setEditingIndex(null);
    setDraft(emptyField());
    if (isBlankNew) {
      emit(next.length > 0 ? next : [emptyField()]);
    }
  };

  const addColumn = () => {
    if (editingIndex !== null) {
      notify({ level: "error", title: "Save or cancel the open column first" });
      return;
    }
    const next = [...localFields, emptyField()];
    setLocalFields(next);
    setEditingIndex(next.length - 1);
    setDraft(emptyField());
  };

  const startEditColumn = (index: number) => {
    const field = localFields[index];
    if (!field) return;
    setEditingIndex(index);
    setDraft({
      ...field,
      columns: field.columns?.map((column) => ({ ...column })) ?? [],
    });
  };

  const removeColumn = (index: number) => {
    if (localFields.length <= 1) return;
    if (editingIndex !== null) {
      notify({ level: "error", title: "Save or cancel the open column first" });
      return;
    }
    emit(localFields.filter((_, i) => i !== index));
  };

  const onDragEnd = (event: DragEndEvent) => {
    if (readOnly || disabled || editingIndex !== null) return;
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = sortableIds.indexOf(String(active.id));
    const newIndex = sortableIds.indexOf(String(over.id));
    if (oldIndex < 0 || newIndex < 0) return;
    emit(arrayMove(localFields, oldIndex, newIndex));
  };

  const locked = readOnly || disabled;

  return (
    <div className="space-y-3" data-help="dx-columns-definition">
      <div>
        <p className="text-sm font-medium text-foreground">
          Columns Definition {!readOnly ? <span className="text-destructive">*</span> : null}
        </p>
        <div className="mt-1 flex flex-wrap items-center gap-2">
          <p className="text-sm text-foreground">Define Table Columns</p>
          <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
            {localFields.length} columns
          </span>
        </div>
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={sortableIds} strategy={rectSortingStrategy}>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {localFields.map((field, index) => {
              const id = sortableIds[index]!;
              const isEditing = editingIndex === index;
              const active = isEditing ? draft : field;

              return (
                <SortableColumnCard key={id} id={id} disabled={locked || editingIndex !== null}>
                  <div
                    className={cn(
                      "rounded-xl border bg-card p-4 shadow-sm",
                      !locked && "pl-9",
                      isEditing ? "border-foreground/25 ring-1 ring-foreground/10" : "border-border",
                    )}
                    data-help="dx-field-card"
                  >
                    {isEditing && !locked ? (
                      <div className="space-y-3">
                        <div>
                          <Label>
                            Column Name <span className="text-destructive">*</span>
                          </Label>
                          <Input
                            className="mt-1"
                            value={draft.label}
                            maxLength={100}
                            placeholder="e.g. Customer Name"
                            onChange={(event) => patchDraft({ label: event.target.value })}
                          />
                          <p className="mt-1 text-right text-[11px] text-muted-foreground">
                            {draft.label.length}/100
                          </p>
                        </div>
                        <div>
                          <Label>Description</Label>
                          <Textarea
                            className="mt-1 min-h-16"
                            value={draft.description ?? ""}
                            maxLength={200}
                            placeholder="e.g. Full name of the customer"
                            onChange={(event) => patchDraft({ description: event.target.value })}
                          />
                          <p className="mt-1 text-right text-[11px] text-muted-foreground">
                            {(draft.description ?? "").length}/200
                          </p>
                        </div>
                        <div>
                          <Label>Type</Label>
                          <Select
                            className="mt-1"
                            value={draft.type}
                            onChange={(event) =>
                              patchDraft({ type: event.target.value as DocExtractFieldType })
                            }
                          >
                            {DOC_EXTRACT_FIELD_TYPES.map((entry) => (
                              <option key={entry.value} value={entry.value}>
                                {entry.short} — {entry.label}
                              </option>
                            ))}
                          </Select>
                        </div>

                        {draft.type === "table" ? (
                          <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
                            <div className="flex items-center justify-between">
                              <p className="text-xs font-medium text-foreground">Table columns</p>
                              <span className="text-[11px] text-muted-foreground">
                                {(draft.columns ?? []).length}/20
                              </span>
                            </div>
                            {(draft.columns ?? []).map((column, columnIndex) => (
                              <div
                                key={columnIndex}
                                className="grid gap-2 sm:grid-cols-[1fr_7rem_auto]"
                              >
                                <Input
                                  value={column.label}
                                  placeholder="Column name"
                                  onChange={(event) =>
                                    patchTableColumn(columnIndex, { label: event.target.value })
                                  }
                                />
                                <Select
                                  value={column.type}
                                  onChange={(event) =>
                                    patchTableColumn(columnIndex, {
                                      type: event.target.value as DocExtractTableColumn["type"],
                                    })
                                  }
                                >
                                  {DOC_EXTRACT_TABLE_COLUMN_TYPES.map((entry) => (
                                    <option key={entry.value} value={entry.value}>
                                      {entry.label}
                                    </option>
                                  ))}
                                </Select>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="ghost"
                                  disabled={(draft.columns ?? []).length <= 1}
                                  onClick={() =>
                                    setDraft((current) => ({
                                      ...current,
                                      columns: (current.columns ?? []).filter(
                                        (_, i) => i !== columnIndex,
                                      ),
                                    }))
                                  }
                                >
                                  <X className="size-4" />
                                </Button>
                              </div>
                            ))}
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={(draft.columns ?? []).length >= 20}
                              onClick={() =>
                                setDraft((current) => ({
                                  ...current,
                                  columns: [...(current.columns ?? []), emptyTableColumn()],
                                }))
                              }
                            >
                              <Plus className="size-4" />
                              Add column
                            </Button>
                          </div>
                        ) : null}

                        <div className="flex gap-2 pt-1">
                          <Button type="button" size="sm" onClick={commitColumn}>
                            <Check className="size-4" />
                            Save
                          </Button>
                          <Button type="button" size="sm" variant="outline" onClick={cancelColumn}>
                            Cancel
                          </Button>
                        </div>
                      </div>
                    ) : (
                      <div className="flex h-full min-h-[10rem] flex-col">
                        <h3 className="text-base font-medium text-foreground">
                          {active.label || "Untitled column"}
                        </h3>
                        <p className="mt-2 min-h-10 text-sm text-muted-foreground">
                          {active.description?.trim() || "No description"}
                        </p>
                        <div className="mt-3">
                          <TypeBadge type={active.type} />
                        </div>
                        {!locked ? (
                          <div className="mt-auto flex gap-3 border-t border-border pt-3">
                            <button
                              type="button"
                              className="inline-flex items-center gap-1 text-xs font-medium text-foreground hover:underline"
                              disabled={editingIndex !== null}
                              onClick={() => startEditColumn(index)}
                            >
                              <Pencil className="size-3.5" />
                              Edit
                            </button>
                            <button
                              type="button"
                              className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-destructive"
                              disabled={localFields.length <= 1 || editingIndex !== null}
                              onClick={() => removeColumn(index)}
                            >
                              <X className="size-3.5" />
                              Remove
                            </button>
                          </div>
                        ) : null}
                      </div>
                    )}
                  </div>
                </SortableColumnCard>
              );
            })}

            {!locked ? (
              <button
                type="button"
                onClick={addColumn}
                disabled={editingIndex !== null}
                className="flex min-h-[14rem] flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/10 p-6 text-center transition-colors hover:border-foreground/30 hover:bg-muted/20 disabled:opacity-50"
              >
                <span className="flex size-10 items-center justify-center rounded-full border border-border bg-card text-foreground">
                  <Plus className="size-5" />
                </span>
                <span className="text-sm font-medium text-foreground">Add Column</span>
                <span className="max-w-[14rem] text-xs text-muted-foreground">
                  Define a new data column to extract
                </span>
              </button>
            ) : null}
          </div>
        </SortableContext>
      </DndContext>
    </div>
  );
}
