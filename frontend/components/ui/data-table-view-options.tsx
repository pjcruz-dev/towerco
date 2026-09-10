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
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Columns3, GripVertical } from "lucide-react";
import type { Column, Table } from "@tanstack/react-table";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

type DataTableViewOptionsProps<TData> = {
  table: Table<TData>;
  /** When true, allow drag-and-drop column reorder. */
  enableReorder?: boolean;
};

function ColumnToggleRow<TData>({
  column,
  dragHandle,
}: {
  column: Column<TData, unknown>;
  dragHandle?: React.ReactNode;
}) {
  const label =
    typeof column.columnDef.header === "string"
      ? column.columnDef.header
      : column.id.replace(/_/g, " ");

  return (
    <li className="flex items-center gap-1 rounded-md">
      {dragHandle}
      <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
        <Checkbox
          className="size-4"
          checked={column.getIsVisible()}
          onCheckedChange={(v) => column.toggleVisibility(v === true)}
        />
        <span className="truncate capitalize">{label}</span>
      </label>
    </li>
  );
}

function SortableColumnRow<TData>({ column }: { column: Column<TData, unknown> }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: column.id,
  });
  const label =
    typeof column.columnDef.header === "string"
      ? column.columnDef.header
      : column.id.replace(/_/g, " ");

  return (
    <li
      ref={setNodeRef}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
      }}
      className={cn(
        "flex items-center gap-1 rounded-md",
        isDragging && "z-10 bg-muted/80 shadow-sm",
      )}
    >
      <button
        type="button"
        className="flex h-7 w-7 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground active:cursor-grabbing"
        title="Drag to reorder"
        aria-label={`Reorder ${label}`}
        {...attributes}
        {...listeners}
      >
        <GripVertical className="h-3.5 w-3.5" />
      </button>
      <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
        <Checkbox
          className="size-4"
          checked={column.getIsVisible()}
          onCheckedChange={(v) => column.toggleVisibility(v === true)}
        />
        <span className="truncate capitalize">{label}</span>
      </label>
    </li>
  );
}

/**
 * Column visibility toggle for TanStack tables (optional drag reorder).
 */
export function DataTableViewOptions<TData>({
  table,
  enableReorder = true,
}: DataTableViewOptionsProps<TData>) {
  const hideable = table.getAllLeafColumns().filter((column) => column.getCanHide());

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor),
  );

  if (hideable.length === 0) {
    return null;
  }

  const order = table.getState().columnOrder;
  const ordered =
    order.length > 0
      ? [...hideable].sort((a, b) => {
          const ai = order.indexOf(a.id);
          const bi = order.indexOf(b.id);
          return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
        })
      : hideable;

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const ids =
      order.length > 0 ? [...order] : table.getAllLeafColumns().map((column) => column.id);
    const oldIndex = ids.indexOf(String(active.id));
    const newIndex = ids.indexOf(String(over.id));
    if (oldIndex < 0 || newIndex < 0) return;
    table.setColumnOrder(arrayMove(ids, oldIndex, newIndex));
  };

  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button type="button" size="sm" variant="outline" className="gap-1.5">
            <Columns3 className="size-3.5" aria-hidden />
            Columns
          </Button>
        }
      />
      <PopoverContent className="w-60 p-2" align="end">
        <p className="mb-2 px-1 text-xs font-medium text-muted-foreground">
          {enableReorder ? "Toggle & drag to reorder" : "Toggle columns"}
        </p>
        {enableReorder ? (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
            <SortableContext
              items={ordered.map((column) => column.id)}
              strategy={verticalListSortingStrategy}
            >
              <ul className="space-y-1">
                {ordered.map((column) => (
                  <SortableColumnRow key={column.id} column={column} />
                ))}
              </ul>
            </SortableContext>
          </DndContext>
        ) : (
          <ul className="space-y-1">
            {ordered.map((column) => (
              <ColumnToggleRow key={column.id} column={column} />
            ))}
          </ul>
        )}
      </PopoverContent>
    </Popover>
  );
}
