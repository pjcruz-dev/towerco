"use client";

import { useEffect, useMemo, useRef, useState, type SyntheticEvent } from "react";
import { createPortal } from "react-dom";
import {
  DndContext,
  PointerSensor,
  closestCorners,
  pointerWithin,
  useDroppable,
  useSensor,
  useSensors,
  type CollisionDetection,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  rectSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable";
import { GripVertical, Lightbulb, Minus, Plus } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { updateDynField, type DynField } from "@/lib/api/modules/dynamic-entities-api";
import {
  addEmptyRow,
  arrangeLayoutsEqual,
  arrangeRowDroppableId,
  clampSpan,
  flattenRowsToPatches,
  isArrangeableField,
  moveFieldInRows,
  packFieldsIntoRows,
  parseArrangeRowDroppableId,
  rowUsed,
  setFieldSpan,
  typeGlyph,
  type ArrangeField,
  type ArrangeRow,
} from "@/lib/dynamic-entities/form-arrange-rows";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  entityName: string;
  fields: DynField[];
  onSaved: () => Promise<void> | void;
};

const arrangeCollision: CollisionDetection = (args) => {
  const pointerHits = pointerWithin(args);
  if (pointerHits.length > 0) {
    // Prefer a field under the cursor; fall back to the row tray.
    const fieldHit = pointerHits.find((h) => !String(h.id).startsWith("arrange-row:"));
    if (fieldHit) return [fieldHit];
    return pointerHits.slice(0, 1);
  }
  return closestCorners(args);
};

export function DynArrangeFormDialog({ open, onOpenChange, entityName, fields, onSaved }: Props) {
  const [rows, setRows] = useState<ArrangeRow[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activeId, setActiveId] = useState<string | null>(null);
  const pointerRef = useRef<{ x: number; y: number } | null>(null);
  const overlayElRef = useRef<HTMLDivElement | null>(null);
  const lastOverIdRef = useRef<string | null>(null);
  const moveLockRef = useRef(false);
  const rowsRef = useRef(rows);
  rowsRef.current = rows;

  const layoutSeed = useMemo(
    () =>
      fields
        .filter(isArrangeableField)
        .map((f) => `${f.id}:${f.field_order}:${f.column_span}`)
        .join("|"),
    [fields],
  );

  useEffect(() => {
    if (!open) return;
    setRows(packFieldsIntoRows(fields));
    setError(null);
    setActiveId(null);
    pointerRef.current = null;
    lastOverIdRef.current = null;
    moveLockRef.current = false;
    // eslint-disable-next-line react-hooks/exhaustive-deps -- layoutSeed fingerprints fields
  }, [open, layoutSeed]);

  // Unlock collision moves on the frame after layout settles (dnd-kit multi-container pattern).
  useEffect(() => {
    if (!activeId) return;
    const id = requestAnimationFrame(() => {
      moveLockRef.current = false;
    });
    return () => cancelAnimationFrame(id);
  }, [rows, activeId]);

  useEffect(() => {
    if (!activeId) return;
    const onMove = (e: PointerEvent) => {
      pointerRef.current = { x: e.clientX, y: e.clientY };
      const el = overlayElRef.current;
      if (el) {
        el.style.transform = `translate3d(${e.clientX + 12}px, ${e.clientY + 12}px, 0)`;
      }
    };
    window.addEventListener("pointermove", onMove);
    return () => window.removeEventListener("pointermove", onMove);
  }, [activeId]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 5 },
    }),
  );

  const fieldIdsByRow = useMemo(
    () => Object.fromEntries(rows.map((r) => [r.id, r.fields.map((f) => f.id)])),
    [rows],
  );

  const activeField = useMemo(() => {
    if (!activeId) return null;
    for (const row of rows) {
      const found = row.fields.find((f) => f.id === activeId);
      if (found) return found;
    }
    return null;
  }, [activeId, rows]);

  function commitMove(activeKey: string, overKey: string) {
    setRows((prev) => {
      // Same-row reorder via flat indices when both are fields in one row — smoother.
      const fromRow = prev.find((r) => r.fields.some((f) => f.id === activeKey));
      const overIsRow = Boolean(parseArrangeRowDroppableId(overKey));
      if (fromRow && !overIsRow && fromRow.fields.some((f) => f.id === overKey)) {
        const ids = fromRow.fields.map((f) => f.id);
        const oldIndex = ids.indexOf(activeKey);
        const newIndex = ids.indexOf(overKey);
        if (oldIndex >= 0 && newIndex >= 0 && oldIndex !== newIndex) {
          const reordered = arrayMove(fromRow.fields, oldIndex, newIndex);
          const next = prev.map((r) => (r.id === fromRow.id ? { ...r, fields: reordered } : r));
          return arrangeLayoutsEqual(prev, next) ? prev : next;
        }
        return prev;
      }

      const next = moveFieldInRows(prev, activeKey, overKey);
      return arrangeLayoutsEqual(prev, next) ? prev : next;
    });
  }

  function onDragStart(event: DragStartEvent) {
    const id = String(event.active.id);
    lastOverIdRef.current = null;
    moveLockRef.current = false;
    setActiveId(id);
    const ev = event.activatorEvent;
    if (ev && "clientX" in ev) {
      const x = (ev as PointerEvent).clientX;
      const y = (ev as PointerEvent).clientY;
      pointerRef.current = { x, y };
    }
  }

  function onDragOver(event: DragOverEvent) {
    const { active, over } = event;
    if (!over || moveLockRef.current) return;

    const activeKey = String(active.id);
    const overKey = String(over.id);
    if (activeKey === overKey) return;
    if (lastOverIdRef.current === overKey) return;

    lastOverIdRef.current = overKey;
    moveLockRef.current = true;
    commitMove(activeKey, overKey);
  }

  function onDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    if (over && String(active.id) !== String(over.id)) {
      commitMove(String(active.id), String(over.id));
    }
    setActiveId(null);
    pointerRef.current = null;
    lastOverIdRef.current = null;
    moveLockRef.current = false;
  }

  function onDragCancel() {
    setActiveId(null);
    pointerRef.current = null;
    lastOverIdRef.current = null;
    moveLockRef.current = false;
  }

  async function save() {
    setSaving(true);
    setError(null);
    try {
      const patches = flattenRowsToPatches(rows);
      const byId = new Map(fields.map((f) => [f.id, f]));
      await Promise.all(
        patches.map(async (patch) => {
          const current = byId.get(patch.id);
          if (
            current &&
            current.field_order === patch.field_order &&
            current.column_span === patch.column_span
          ) {
            return;
          }
          await updateDynField(patch.id, {
            field_order: patch.field_order,
            column_span: patch.column_span,
          });
        }),
      );
      await onSaved();
      onOpenChange(false);
    } catch {
      setError("Unable to save arrangement. Try again.");
    } finally {
      setSaving(false);
    }
  }

  const overlayPos = pointerRef.current;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        showCloseButton
        className={cn(
          "flex max-h-[min(92vh,900px)] w-[min(calc(100vw-2rem),1080px)] flex-col gap-0 overflow-hidden p-0 sm:max-w-none",
          "!inset-0 !top-0 !right-0 !bottom-0 !left-0 !m-auto !h-fit !translate-x-0 !translate-y-0 !scale-100",
          "data-[starting-style]:!scale-100 data-[ending-style]:!scale-100 data-[starting-style]:!opacity-100",
        )}
      >
        <DialogHeader className="pr-12">
          <DialogTitle>Arrange Form</DialogTitle>
          <DialogDescription>Drag fields into rows — {entityName}</DialogDescription>
        </DialogHeader>

        <DialogBody className="space-y-4">
          <div className="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
            <Lightbulb className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <p>
              Drag a card by its handle to move it within a row or into another row. The slider sets
              how much of the 12-column grid it takes, so three cards at 4 each fill one row.
            </p>
          </div>

          <DndContext
            sensors={sensors}
            collisionDetection={arrangeCollision}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragEnd={onDragEnd}
            onDragCancel={onDragCancel}
          >
            <div className="space-y-4">
              {rows.map((row, index) => (
                <ArrangeRowBlock
                  key={row.id}
                  row={row}
                  index={index}
                  fieldIds={fieldIdsByRow[row.id] ?? []}
                  activeId={activeId}
                  onSpanChange={(fieldId, span) =>
                    setRows((prev) => setFieldSpan(prev, fieldId, span))
                  }
                />
              ))}
            </div>
          </DndContext>

          {typeof document !== "undefined" && activeField && overlayPos
            ? createPortal(
                <div
                  ref={overlayElRef}
                  className="pointer-events-none fixed top-0 left-0 z-[200] w-52 will-change-transform"
                  style={{
                    transform: `translate3d(${overlayPos.x + 12}px, ${overlayPos.y + 12}px, 0)`,
                  }}
                >
                  <FieldCardPreview field={activeField} dragging />
                </div>,
                document.body,
              )
            : null}

          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => setRows((prev) => addEmptyRow(prev))}
          >
            <Plus className="size-3.5" />
            Add empty row
          </Button>

          {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </DialogBody>

        <DialogFooter className="sm:justify-between">
          <Button
            type="button"
            variant="ghost"
            disabled={saving}
            onClick={() => onOpenChange(false)}
          >
            Discard
          </Button>
          <Button type="button" disabled={saving} onClick={() => void save()}>
            {saving ? "Saving…" : "Save arrangement"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ArrangeRowBlock({
  row,
  index,
  fieldIds,
  activeId,
  onSpanChange,
}: {
  row: ArrangeRow;
  index: number;
  fieldIds: string[];
  activeId: string | null;
  onSpanChange: (fieldId: string, span: number) => void;
}) {
  const droppableId = arrangeRowDroppableId(row.id);
  const { setNodeRef, isOver } = useDroppable({ id: droppableId });
  const used = rowUsed(row);
  const overflow = used > 12;

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between gap-2">
        <span className="text-[11px] font-medium tracking-wide text-muted-foreground">
          ROW {index + 1}
        </span>
        <span
          className={cn(
            "text-[11px] tabular-nums text-muted-foreground",
            overflow && "font-medium text-destructive",
          )}
        >
          {used}/12
        </span>
      </div>
      <div
        ref={setNodeRef}
        className={cn(
          "min-h-[7rem] rounded-xl border border-dashed border-border bg-muted/15 p-3 transition-colors duration-150",
          isOver && "border-sky-400 bg-sky-50/60 dark:bg-sky-950/25",
          overflow && "border-destructive/50",
        )}
      >
        <SortableContext items={fieldIds} strategy={rectSortingStrategy}>
          <div className="grid grid-cols-12 gap-3">
            {row.fields.map((field) => (
              <SortableFieldCard
                key={field.id}
                field={field}
                isActive={activeId === field.id}
                onSpanChange={(span) => onSpanChange(field.id, span)}
              />
            ))}
          </div>
        </SortableContext>
        {row.fields.length === 0 ? (
          <p className="flex w-full items-center justify-center py-8 text-xs text-muted-foreground">
            Drop a field here
          </p>
        ) : null}
      </div>
    </div>
  );
}

function SortableFieldCard({
  field,
  isActive,
  onSpanChange,
}: {
  field: ArrangeField;
  isActive: boolean;
  onSpanChange: (span: number) => void;
}) {
  const { attributes, listeners, setNodeRef, isDragging } = useSortable({
    id: field.id,
  });

  const span = clampSpan(field.column_span);
  const showPlaceholder = isActive || isDragging;

  return (
    <div
      ref={setNodeRef}
      style={{ gridColumn: `span ${span} / span ${span}` }}
      className={cn("min-w-0 touch-none", !showPlaceholder && "cursor-grab active:cursor-grabbing")}
      {...attributes}
      {...listeners}
    >
      {showPlaceholder ? (
        <div
          className="flex min-h-[6.25rem] items-center justify-center rounded-lg border-2 border-dashed border-sky-400 bg-sky-50/70 dark:border-sky-500 dark:bg-sky-950/30"
          aria-hidden
        />
      ) : (
        <FieldCardPreview field={field} onSpanChange={onSpanChange} />
      )}
    </div>
  );
}

function stopDragSteal(e: SyntheticEvent) {
  e.stopPropagation();
}

function FieldCardPreview({
  field,
  dragging,
  onSpanChange,
}: {
  field: ArrangeField;
  dragging?: boolean;
  onSpanChange?: (span: number) => void;
}) {
  return (
    <div
      className={cn(
        "flex h-full min-h-[6.25rem] flex-col rounded-lg border border-border bg-card p-3 shadow-sm transition-shadow duration-150",
        "hover:border-sky-300 hover:bg-sky-50/40 dark:hover:border-sky-700 dark:hover:bg-sky-950/20",
        dragging && "shadow-lg ring-2 ring-sky-400",
      )}
    >
      <div className="flex items-start gap-2 rounded-md">
        <span className="inline-flex size-9 shrink-0 items-center justify-center text-muted-foreground">
          <GripVertical className="size-5" />
        </span>
        <div className="min-w-0 flex-1 pt-1.5">
          <div className="truncate text-sm font-medium text-foreground">{field.label}</div>
          <div className="truncate font-mono text-[11px] text-muted-foreground">{field.name}</div>
        </div>
        <span
          className="mt-1 inline-flex size-7 shrink-0 items-center justify-center rounded-md border border-border bg-muted/50 text-[10px] font-medium text-muted-foreground"
          title={field.type}
        >
          {typeGlyph(field.type)}
        </span>
      </div>

      {onSpanChange ? (
        <div
          className="mt-auto space-y-1.5 pt-3"
          onPointerDown={stopDragSteal}
          onMouseDown={stopDragSteal}
          onTouchStart={stopDragSteal}
        >
          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              variant="outline"
              size="icon-sm"
              className="size-8 shrink-0"
              aria-label={`Narrower ${field.label}`}
              disabled={field.column_span <= 1}
              onClick={() => onSpanChange(field.column_span - 1)}
            >
              <Minus className="size-3.5" />
            </Button>
            <input
              type="range"
              min={1}
              max={12}
              step={1}
              value={field.column_span}
              onChange={(e) => onSpanChange(Number(e.target.value))}
              onPointerDown={stopDragSteal}
              className="dyn-arrange-span-slider min-w-0 flex-1 cursor-pointer"
              aria-label={`${field.label} width`}
            />
            <Button
              type="button"
              variant="outline"
              size="icon-sm"
              className="size-8 shrink-0"
              aria-label={`Wider ${field.label}`}
              disabled={field.column_span >= 12}
              onClick={() => onSpanChange(field.column_span + 1)}
            >
              <Plus className="size-3.5" />
            </Button>
            <span className="w-9 shrink-0 text-right text-xs tabular-nums text-muted-foreground">
              {field.column_span}/12
            </span>
          </div>
        </div>
      ) : (
        <div className="mt-auto pt-3 text-xs tabular-nums text-muted-foreground">
          {field.column_span}/12
        </div>
      )}
    </div>
  );
}
