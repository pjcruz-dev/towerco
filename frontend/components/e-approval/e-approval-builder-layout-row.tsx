"use client";



import { useDroppable } from "@dnd-kit/core";

import { Columns2, GripVertical, Trash2 } from "lucide-react";
import type { DraggableAttributes, DraggableSyntheticListeners } from "@dnd-kit/core";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";

import type { ReactNode } from "react";



import { cn } from "@/lib/utils";

import {

  layoutRowBuilderGridClass,

  layoutRowBuilderSlotClass,

  layoutRowSlotDroppableId,

  type EApprovalLayoutRowColumns,

} from "@/modules/e-approval/field-layout";



function LayoutSlot({

  rowId,

  slot,

  children,

  isOver,

  columns,

  disableDrops = false,

}: {

  rowId: string;

  slot: number;

  children: ReactNode;

  isOver?: boolean;

  columns: EApprovalLayoutRowColumns;

  disableDrops?: boolean;

}) {

  const id = layoutRowSlotDroppableId(rowId, slot);

  const { setNodeRef, isOver: isOverSlot } = useDroppable({ id, disabled: disableDrops });



  const active = isOver || isOverSlot;



  return (
    <div
      ref={setNodeRef}
      className={cn(
        layoutRowBuilderSlotClass(columns),
        "flex min-h-[72px] flex-col",
      )}
    >
      <div
        className={cn(
          "flex min-h-[72px] flex-1 flex-col gap-2 rounded-lg border border-dashed p-2 transition-colors",
          active ? "z-10 border-foreground/30 bg-muted/40" : "border-border bg-card",
          children ? "border-solid" : "",
        )}
      >
        {children}
        <p
          className={cn(
            "flex items-center justify-center px-1 text-center text-xs text-muted-foreground",
            children ? "min-h-8 rounded-md border border-dashed border-border/70 bg-muted/20 py-1.5" : "flex-1",
          )}
        >
          {children ? `Drop another field (col ${slot + 1})` : `Drop field (slot ${slot + 1})`}
        </p>
      </div>
    </div>
  );

}



type Props = {
  rowId: string;
  columnCount: EApprovalLayoutRowColumns;
  slots: ReactNode[];
  activeDragOverSlot?: number | null;
  onRemoveEmptyRow?: () => void;
  onColumnCountChange?: (columns: EApprovalLayoutRowColumns) => void;
  /** When set, drag the whole row up/down on the form canvas. */
  dragHandleAttributes?: DraggableAttributes;
  dragHandleListeners?: DraggableSyntheticListeners;
  isDraggingRow?: boolean;
  /** Disable column slot drops while reordering rows/fields on the canvas. */
  disableSlotDrops?: boolean;
};

export function EApprovalBuilderLayoutRow({
  rowId,
  columnCount,
  slots,
  activeDragOverSlot,
  onRemoveEmptyRow,
  onColumnCountChange,
  dragHandleAttributes,
  dragHandleListeners,
  isDraggingRow = false,
  disableSlotDrops = false,
}: Props) {
  const slotCount = Math.max(columnCount, slots.length);
  const isEmpty = slots.every((slot) => slot == null);

  const canDragRow = Boolean(dragHandleAttributes && dragHandleListeners);

  return (
    <div
      className={cn(
        "space-y-2 rounded-xl border border-border bg-muted/20 p-3 transition-shadow",
        isDraggingRow && "opacity-80 shadow-md ring-2 ring-primary/30",
      )}
    >
      <div className="flex items-center justify-between gap-2 text-xs font-medium text-muted-foreground">
        <span className="flex min-w-0 items-center gap-2">
          {canDragRow ? (
            <button
              type="button"
              className="cursor-grab touch-none rounded p-0.5 text-muted-foreground hover:text-foreground active:cursor-grabbing"
              {...dragHandleAttributes}
              {...dragHandleListeners}
              aria-label={`Drag ${columnCount}-column row to reorder`}
            >
              <GripVertical className="h-4 w-4 shrink-0" />
            </button>
          ) : (
            <Columns2 className="h-3.5 w-3.5 shrink-0" />
          )}
          <span className="truncate">
            {columnCount}-column row
            {canDragRow ? <span className="font-normal text-muted-foreground/80"> — drag to reorder</span> : null}
          </span>
        </span>
        <span className="flex shrink-0 items-center gap-1">
          {onColumnCountChange ? (
            <Select
              aria-label="Column count"
              className="h-7 w-[4.5rem] text-xs"
              value={String(columnCount)}
              onChange={(e) => {
                const next = Number(e.target.value);
                if (next === 2 || next === 3 || next === 4) {
                  onColumnCountChange(next);
                }
              }}
            >
              <option value="2">2 col</option>
              <option value="3">3 col</option>
              <option value="4">4 col</option>
            </Select>
          ) : null}
          {onRemoveEmptyRow && isEmpty ? (
            <Button
              type="button"
              size="icon"
              variant="ghost"
              className="h-7 w-7 text-destructive"
              onClick={onRemoveEmptyRow}
              aria-label="Remove empty row"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          ) : null}
        </span>
      </div>

      <div className={layoutRowBuilderGridClass(columnCount)}>

        {Array.from({ length: slotCount }, (_, slot) => (

          <LayoutSlot

            key={slot}

            rowId={rowId}

            slot={slot}

            columns={columnCount}

            isOver={activeDragOverSlot === slot}

            disableDrops={disableSlotDrops}

          >

            {slots[slot] ?? null}

          </LayoutSlot>

        ))}

      </div>

    </div>

  );

}

