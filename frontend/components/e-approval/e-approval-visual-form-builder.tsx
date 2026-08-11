"use client";

import {
  DndContext,
  DragOverlay,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  pointerWithin,
  rectIntersection,
  useDroppable,
  useSensor,
  useSensors,
  type Collision,
  type CollisionDetection,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ChevronDown, ChevronUp, Columns2, Copy, GripVertical, Settings2, Trash2 } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";

import { EApprovalBuilderCanvasOutline } from "@/components/e-approval/e-approval-builder-canvas-outline";
import { EApprovalBuilderFieldSearch } from "@/components/e-approval/e-approval-builder-field-search";
import { EApprovalComposeStepNav } from "@/components/e-approval/e-approval-compose-step-nav";
import { EApprovalBuilderLayoutRow } from "@/components/e-approval/e-approval-builder-layout-row";
import {
  catalogPickIcon,
  catalogPickLabel,
  type EApprovalCatalogPick,
} from "@/components/e-approval/e-approval-field-catalog-shared";
import { EApprovalFieldCatalogPalette } from "@/components/e-approval/e-approval-field-catalog-palette";
import { EApprovalFieldPropertiesPanel } from "@/components/e-approval/e-approval-field-properties-panel";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  applySortableOrderToLayoutRows,
  buildBuilderGroupSegments,
  buildCanvasSortableIds,
  bumpBuilderLayoutRowInsertIndices,
  canvasFieldSortableId,
  flattenSortableOrderToFields,
  resolveFieldInsertIndexFromCanvasTarget,
  sectionGroupInsertIndex,
  type EApprovalBuilderCanvasSegment,
  type EApprovalBuilderLayoutRow as EApprovalBuilderLayoutRowScaffold,
} from "@/modules/e-approval/builder-layout-rows";
import {
  buildFormFieldBundle,
  getFormFieldBundleMetadataPatch,
  parseFormFieldBundleCatalogId,
  type EApprovalFormFieldBundleId,
} from "@/modules/e-approval/custom-form-presets";
import { suggestApiKeyFromLabel, collectFieldApiKeys } from "@/modules/e-approval/field-api-key";
import { formatInstructionBodyForDisplay, parseInstructionBody } from "@/modules/e-approval/field-instruction";
import { parseFieldVisibility } from "@/modules/e-approval/field-visibility";
import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import {
  buildBuilderCanvasOutline,
  buildBuilderCanvasOutlineFromComposeSteps,
  builderCanvasSectionAnchorId,
} from "@/modules/e-approval/builder-canvas-outline";
import {
  findDisplayGroupIndexForField,
  isLargeBuilderForm,
  shouldForceBuilderCanvasOutline,
  shouldShowBuilderFieldSearch,
} from "@/modules/e-approval/builder-canvas-performance";
import {
  buildBuilderFieldSearchIndex,
  builderCanvasFieldAnchorId,
} from "@/modules/e-approval/builder-field-search";
import {
  buildFormComposeDesignSummary,
  type FormComposeEditorSettings,
} from "@/modules/e-approval/form-compose-config";
import {
  buildBuilderStepVisibleIndices,
  buildDisplayGroupsForComposeStep,
  buildFormComposeSteps,
  filterDisplayGroupsForStepIndices,
} from "@/modules/e-approval/form-compose-steps";
import type { EApprovalFieldListEntry } from "@/modules/e-approval/form-field-groups";
import {
  createLayoutRowId,
  E_APPROVAL_CANVAS_DROP_END_ID,
  FIELD_LAYOUT_WIDTH_LABELS,
  fieldSupportsLayout,
  findLayoutRowInsertIndex,
  moveFieldInRowSlot,
  nextStackOrderForRowSlot,
  inferLayoutRowColumnCount,
  layoutWidthForRowColumns,
  isCatalogDragId,
  parseCatalogFieldDragId,
  parseCatalogRowDragId,
  parseFieldLayout,
  assignEntriesToRowSlots,
  clampLayoutRowSlot,
  fieldLayoutsChanged,
  findNextAvailableRowSlot,
  isLayoutRowSlotDroppableId,
  isSectionDroppableId,
  normalizeFormFieldLayouts,
  layoutRowBlockDragId,
  parseLayoutRowBlockDragId,
  parseLayoutRowSlotDroppableId,
  parseSectionDroppableId,
  patchFieldLayout,
  sectionDroppableId,
  detachFieldFromRowLayout,
  type EApprovalLayoutRowColumns,
} from "@/modules/e-approval/field-layout";
import {
  defaultFieldForType,
  formatEApprovalFieldTypeLabel,
  type EApprovalFieldType,
} from "@/modules/e-approval/field-types";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { E_APPROVAL_COMPOSE_SHELL_CLASS } from "@/modules/e-approval/form-layout";
import { useEApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = {
  fields: EApprovalFormFieldInput[];
  onFieldsChange: (fields: EApprovalFormFieldInput[]) => void;
  layoutRows: EApprovalBuilderLayoutRowScaffold[];
  onLayoutRowsChange: (rows: EApprovalBuilderLayoutRowScaffold[]) => void;
  onMetadataPatch?: (patch: Record<string, unknown>) => void;
  apiKeysLocked?: boolean;
  composeSettings?: FormComposeEditorSettings;
};

function layoutSlotCollisions(collisions: Collision[]): Collision[] {
  return collisions.filter((c) => isLayoutRowSlotDroppableId(String(c.id)));
}

/** When several slots overlap in the hit list, pick the slot whose center is nearest the pointer. */
function pickNearestLayoutSlotCollision(collisions: Collision[], pointer: { x: number; y: number } | null): Collision[] {
  const slotHits = layoutSlotCollisions(collisions);
  if (slotHits.length === 0) {
    return [];
  }
  if (slotHits.length === 1 || !pointer) {
    return slotHits;
  }

  let best = slotHits[0]!;
  let bestDist = Number.POSITIVE_INFINITY;

  for (const hit of slotHits) {
    const rect = hit.data?.droppableContainer?.rect;
    if (!rect) {
      continue;
    }
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const dist = (pointer.x - cx) ** 2 + (pointer.y - cy) ** 2;
    if (dist < bestDist) {
      bestDist = dist;
      best = hit;
    }
  }

  return [best];
}

function pointerNearLayoutSlot(
  pointer: { x: number; y: number },
  rect: { left: number; top: number; width: number; height: number },
  padding = 12,
): boolean {
  return (
    pointer.x >= rect.left - padding &&
    pointer.x <= rect.left + rect.width + padding &&
    pointer.y >= rect.top - padding &&
    pointer.y <= rect.top + rect.height + padding
  );
}

function isCanvasReorderDragId(activeId: string, sortableIds: string[]): boolean {
  return parseLayoutRowBlockDragId(activeId) !== null || sortableIds.includes(activeId);
}

function isReorderDropTargetId(id: string, sortableIds: string[]): boolean {
  return (
    id === E_APPROVAL_CANVAS_DROP_END_ID ||
    parseLayoutRowBlockDragId(id) !== null ||
    sortableIds.includes(id)
  );
}

function filterReorderCollisions(collisions: Collision[], sortableIds: string[]): Collision[] {
  return collisions.filter((c) => isReorderDropTargetId(String(c.id), sortableIds));
}

function resolveCanvasReorderOverId(
  overId: string,
  sortableIds: string[],
  activeId: string,
): string | null {
  const activeRowId = parseLayoutRowBlockDragId(activeId);

  const slotTarget = parseLayoutRowSlotDroppableId(overId);
  if (slotTarget) {
    if (!activeRowId) {
      return null;
    }
    if (activeRowId && slotTarget.rowId === activeRowId) {
      return null;
    }
    const rowBlockId = layoutRowBlockDragId(slotTarget.rowId);
    if (sortableIds.includes(rowBlockId) && rowBlockId !== activeId) {
      return rowBlockId;
    }
    return null;
  }

  if (isReorderDropTargetId(overId, sortableIds) && overId !== activeId) {
    return overId;
  }

  return null;
}

function detectLayoutSlotCollisions(
  args: Parameters<CollisionDetection>[0],
): Collision[] {
  const pointer = args.pointerCoordinates ?? null;
  const slotContainers = args.droppableContainers.filter((c) =>
    isLayoutRowSlotDroppableId(String(c.id)),
  );

  const pointerHits = pickNearestLayoutSlotCollision(pointerWithin(args), pointer);
  if (pointerHits.length > 0) {
    return pointerHits;
  }

  const rectHits = pickNearestLayoutSlotCollision(rectIntersection(args), pointer);
  if (rectHits.length > 0) {
    return rectHits;
  }

  if (pointer && slotContainers.length > 0) {
    const closestHits = closestCenter({
      ...args,
      droppableContainers: slotContainers,
    });
    const nearest = pickNearestLayoutSlotCollision(closestHits, pointer);
    const rect = nearest[0]?.data?.droppableContainer?.rect;
    if (rect && pointerNearLayoutSlot(pointer, rect)) {
      return nearest;
    }
  }

  return [];
}

function buildReorderOnlyDroppableContainers(
  containers: Parameters<CollisionDetection>[0]["droppableContainers"],
  sortableIds: string[],
  activeId: string,
) {
  const activeRowId = parseLayoutRowBlockDragId(activeId);

  return containers.filter((container) => {
    const id = String(container.id);
    if (isLayoutRowSlotDroppableId(id)) {
      if (activeRowId) {
        const slot = parseLayoutRowSlotDroppableId(id);
        if (slot?.rowId === activeRowId) {
          return false;
        }
      }
      return false;
    }
    return isReorderDropTargetId(id, sortableIds);
  });
}

function pickReorderOverFromCollisions(
  collisions: Collision[] | undefined,
  sortableIds: string[],
  activeId: string,
): string | null {
  if (!collisions?.length) {
    return null;
  }

  for (const collision of collisions) {
    const resolved = resolveCanvasReorderOverId(String(collision.id), sortableIds, activeId);
    if (resolved) {
      return resolved;
    }
  }

  return null;
}

function fieldKey(field: EApprovalFormFieldInput, index: number): string {
  return canvasFieldSortableId(field, index);
}

function CanvasDropZone({ active, className }: { active: boolean; className?: string }) {
  const { setNodeRef, isOver } = useDroppable({ id: E_APPROVAL_CANVAS_DROP_END_ID });

  return (
    <div
      ref={setNodeRef}
      className={cn(
        "rounded-lg border border-dashed px-3 py-4 text-center text-xs text-muted-foreground transition-colors",
        active || isOver ? "border-primary bg-primary/5 text-primary" : "border-border/80",
        className,
      )}
    >
      {active || isOver
        ? "Release to drop on the form"
        : "Drop fields or row layouts here to build the form"}
    </div>
  );
}

/** Section body drop target — catalog drops land inside this section, not the previous one. */
function SectionBodyDropZone({
  groupIndex,
  active,
  className,
  children,
}: {
  groupIndex: number;
  active: boolean;
  className?: string;
  children: ReactNode;
}) {
  const { setNodeRef, isOver } = useDroppable({ id: sectionDroppableId(groupIndex) });

  return (
    <div
      ref={setNodeRef}
      className={cn(
        className,
        active && isOver && "rounded-lg ring-2 ring-primary/35 ring-offset-2 ring-offset-background",
      )}
    >
      {children}
    </div>
  );
}

function FieldRowCard({
  field,
  selected,
  onSelect,
  onRemove,
  onDuplicate,
  onMoveUp,
  onMoveDown,
  dragHandle,
}: {
  field: EApprovalFormFieldInput;
  selected: boolean;
  onSelect: () => void;
  onRemove: () => void;
  onDuplicate: () => void;
  onMoveUp: () => void;
  onMoveDown: () => void;
  dragHandle?: ReactNode;
}) {
  const instructionBody =
    field.type === "instruction"
      ? formatInstructionBodyForDisplay(parseInstructionBody(field)).trim()
      : "";

  return (
    <div
      className={cn(
        "rounded-lg border bg-card p-2 text-sm touch-manipulation",
        field.type === "page_break" && "border-dashed border-border bg-muted/30",
        field.type === "instruction" && "border-border/70 bg-muted/20",
        selected ? "border-foreground/25 bg-muted/40" : "border-border",
      )}
    >
      <div className="flex items-center gap-2">
        {dragHandle ?? <div className="w-6 shrink-0" aria-hidden />}
        <button type="button" className="min-w-0 flex-1 text-left" onClick={onSelect}>
          <span className="font-medium">{field.label}</span>
          <span className="ml-2 text-xs text-muted-foreground">
            {formatEApprovalFieldTypeLabel(field.type)} · {field.name}
          </span>
          {(() => {
            const layout = parseFieldLayout(field);
            if (layout.width === "full" && !layout.row_id) {
              return null;
            }
            return (
              <span className="ml-2 inline-flex gap-1">
                {layout.width !== "full" ? (
                  <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium">
                    {FIELD_LAYOUT_WIDTH_LABELS[layout.width].split("—")[0]?.trim()}
                  </span>
                ) : null}
                {layout.row_id ? (
                  <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-foreground">Row</span>
                ) : null}
                {parseFieldVisibility(field) ? (
                  <span className="rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-medium text-amber-900 dark:text-amber-100">
                    Conditional
                  </span>
                ) : null}
              </span>
            );
          })()}
        </button>
        <Button type="button" size="icon" variant="ghost" className="h-8 w-8 shrink-0" onClick={onDuplicate} aria-label="Duplicate">
          <Copy className="h-4 w-4" />
        </Button>
        <Button type="button" size="icon" variant="ghost" className="h-8 w-8 shrink-0 md:inline-flex" onClick={onMoveUp} aria-label="Move up">
          <ChevronUp className="h-4 w-4" />
        </Button>
        <Button type="button" size="icon" variant="ghost" className="h-8 w-8 shrink-0 md:inline-flex" onClick={onMoveDown} aria-label="Move down">
          <ChevronDown className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          size="icon"
          variant="ghost"
          className="h-8 w-8 shrink-0 text-destructive"
          onClick={onRemove}
          aria-label="Remove"
        >
          <Trash2 className="h-4 w-4" />
        </Button>
      </div>
      {instructionBody ? (
        <button
          type="button"
          className="mt-2 w-full rounded-md border border-border/50 bg-background/80 px-2.5 py-2 text-left"
          onClick={onSelect}
        >
          <p className="line-clamp-4 whitespace-normal break-words text-xs leading-relaxed text-muted-foreground">
            {instructionBody}
          </p>
        </button>
      ) : null}
    </div>
  );
}

function SortableFieldRow({
  field,
  index,
  selected,
  onSelect,
  onRemove,
  onDuplicate,
  onMoveUp,
  onMoveDown,
}: {
  field: EApprovalFormFieldInput;
  index: number;
  selected: boolean;
  onSelect: () => void;
  onRemove: () => void;
  onDuplicate: () => void;
  onMoveUp: () => void;
  onMoveDown: () => void;
}) {
  const id = fieldKey(field, index);
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    animateLayoutChanges: () => false,
  });

  return (
    <div
      id={builderCanvasFieldAnchorId(index)}
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn("scroll-mt-24", isDragging && "opacity-70 shadow-md")}
    >
      <FieldRowCard
        field={field}
        selected={selected}
        onSelect={onSelect}
        onRemove={onRemove}
        onDuplicate={onDuplicate}
        onMoveUp={onMoveUp}
        onMoveDown={onMoveDown}
        dragHandle={
          <button
            type="button"
            className="cursor-grab touch-none p-1 text-muted-foreground active:cursor-grabbing"
            {...attributes}
            {...listeners}
            aria-label="Drag to reorder"
          >
            <GripVertical className="h-4 w-4" />
          </button>
        }
      />
    </div>
  );
}

function SortableLayoutRowBlock({
  rowId,
  columnCount,
  activeDragOverSlot,
  slots,
  onRemoveEmptyRow,
  onColumnCountChange,
  disableSlotDrops = false,
}: {
  rowId: string;
  columnCount: EApprovalLayoutRowColumns;
  activeDragOverSlot: number | null;
  slots: ReactNode[];
  onRemoveEmptyRow?: () => void;
  onColumnCountChange?: (columns: EApprovalLayoutRowColumns) => void;
  disableSlotDrops?: boolean;
}) {
  const id = layoutRowBlockDragId(rowId);
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    animateLayoutChanges: () => false,
  });

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn("touch-manipulation", isDragging && "relative z-20")}
    >
      <EApprovalBuilderLayoutRow
        rowId={rowId}
        columnCount={columnCount}
        activeDragOverSlot={activeDragOverSlot}
        slots={slots}
        dragHandleAttributes={attributes}
        dragHandleListeners={listeners}
        isDraggingRow={isDragging}
        onRemoveEmptyRow={onRemoveEmptyRow}
        onColumnCountChange={onColumnCountChange}
        disableSlotDrops={disableSlotDrops}
      />
    </div>
  );
}

export function EApprovalVisualFormBuilder({
  fields,
  onFieldsChange,
  layoutRows,
  onLayoutRowsChange,
  onMetadataPatch,
  apiKeysLocked = false,
  composeSettings,
}: Props) {
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [propertiesOpen, setPropertiesOpen] = useState(false);
  const [dragOverSlot, setDragOverSlot] = useState<{ rowId: string; slot: number } | null>(null);
  /** Full dnd active id while dragging from the catalog (field or row). */
  const [activeCatalogDragId, setActiveCatalogDragId] = useState<string | null>(null);
  const [activeCanvasReorderId, setActiveCanvasReorderId] = useState<string | null>(null);
  const [collapsedGroups, setCollapsedGroups] = useState<Set<number>>(() => new Set());
  const [activeOutlineGroup, setActiveOutlineGroup] = useState<number | null>(null);
  const [activeBuilderStepIndex, setActiveBuilderStepIndex] = useState(0);
  const canvasScrollRef = useRef<HTMLDivElement>(null);
  const didAutoCollapseLargeForm = useRef(false);
  const selectedField = fields[selectedIndex] ?? null;
  const plan = useEApprovalPlanFeatures();
  const push = useNotificationStore((s) => s.push);

  const displayGroups = useMemo(() => buildFieldDisplayGroups(fields), [fields]);
  const largeFormMode = useMemo(() => isLargeBuilderForm(fields), [fields]);
  const showFieldSearch = useMemo(() => shouldShowBuilderFieldSearch(fields), [fields]);
  const fieldSearchIndex = useMemo(
    () => buildBuilderFieldSearchIndex(fields, displayGroups),
    [fields, displayGroups],
  );
  const composeSummary = useMemo(
    () =>
      composeSettings
        ? buildFormComposeDesignSummary(composeSettings, fields)
        : null,
    [composeSettings, fields],
  );
  const steppedCanvas = composeSummary?.steppedActive === true;
  const composeSteps = useMemo(() => {
    if (!steppedCanvas || !composeSettings) {
      return [];
    }

    return buildFormComposeSteps(fields, composeSettings.stepSource, { includeEmptySteps: true });
  }, [steppedCanvas, composeSettings, fields]);

  useEffect(() => {
    if (composeSteps.length === 0) {
      return;
    }

    setActiveBuilderStepIndex((index) => Math.min(index, composeSteps.length - 1));
  }, [composeSteps.length]);

  useEffect(() => {
    if (!steppedCanvas) {
      return;
    }

    setCollapsedGroups(new Set());
  }, [activeBuilderStepIndex, steppedCanvas]);

  const canvasVisibleIndices = useMemo(() => {
    if (!steppedCanvas || composeSteps.length === 0) {
      return null;
    }

    const step = composeSteps[activeBuilderStepIndex];
    if (!step) {
      return null;
    }

    return buildBuilderStepVisibleIndices(step, fields);
  }, [steppedCanvas, composeSteps, activeBuilderStepIndex, fields]);

  const canvasDisplayGroups = useMemo(() => {
    if (!steppedCanvas || composeSteps.length === 0) {
      return displayGroups;
    }

    const step = composeSteps[activeBuilderStepIndex];
    if (!step) {
      return displayGroups;
    }

    const fromStep = buildDisplayGroupsForComposeStep(step, fields);
    if (fromStep.length > 0) {
      return fromStep;
    }

    if (!canvasVisibleIndices) {
      return displayGroups;
    }

    return filterDisplayGroupsForStepIndices(displayGroups, canvasVisibleIndices);
  }, [
    activeBuilderStepIndex,
    canvasVisibleIndices,
    composeSteps,
    displayGroups,
    fields,
    steppedCanvas,
  ]);

  const canvasOutline = useMemo(() => {
    if (steppedCanvas && composeSteps.length >= 2) {
      return buildBuilderCanvasOutlineFromComposeSteps(composeSteps, fields);
    }

    return buildBuilderCanvasOutline(canvasDisplayGroups, { stepped: false });
  }, [canvasDisplayGroups, composeSteps, fields, steppedCanvas]);
  const showCanvasOutline = useMemo(() => {
    // Stepped forms always show outline so shorter steps (e.g. Step 2) stay navigable.
    if (steppedCanvas && composeSteps.length >= 2) {
      return true;
    }

    return shouldForceBuilderCanvasOutline(fields, canvasDisplayGroups);
  }, [canvasDisplayGroups, composeSteps.length, fields, steppedCanvas]);
  const sortableIds = useMemo(
    () => buildCanvasSortableIds(fields, layoutRows, canvasDisplayGroups),
    [fields, layoutRows, canvasDisplayGroups],
  );
  useEffect(() => {
    if (!largeFormMode || steppedCanvas || didAutoCollapseLargeForm.current || displayGroups.length === 0) {
      return;
    }

    didAutoCollapseLargeForm.current = true;
    setCollapsedGroups(new Set(displayGroups.map((_, groupIndex) => groupIndex)));
  }, [displayGroups, largeFormMode, steppedCanvas]);

  useEffect(() => {
    if (!steppedCanvas) {
      return;
    }

    setActiveOutlineGroup(activeBuilderStepIndex);
  }, [activeBuilderStepIndex, steppedCanvas]);

  const isCatalogDragging = Boolean(activeCatalogDragId);
  const isRowBlockReorderDragging =
    activeCanvasReorderId !== null && parseLayoutRowBlockDragId(activeCanvasReorderId) !== null;

  const collisionDetection = useCallback<CollisionDetection>(
    (args) => {
      const activeId = String(args.active.id);
      const catalogDrag = isCatalogDragId(activeId) || parseCatalogRowDragId(activeId) !== null;
      const rowBlockDrag = parseLayoutRowBlockDragId(activeId) !== null;

      if (!catalogDrag && !rowBlockDrag) {
        const slotHits = detectLayoutSlotCollisions(args);
        if (slotHits.length > 0) {
          return slotHits;
        }
      }

      if (!catalogDrag) {
        const reorderArgs = {
          ...args,
          droppableContainers: buildReorderOnlyDroppableContainers(
            args.droppableContainers,
            sortableIds,
            activeId,
          ),
        };

        const pointerHits = filterReorderCollisions(pointerWithin(reorderArgs), sortableIds);
        if (pointerHits.length > 0) {
          return pointerHits;
        }

        const rectHits = filterReorderCollisions(rectIntersection(reorderArgs), sortableIds);
        if (rectHits.length > 0) {
          return rectHits;
        }

        return filterReorderCollisions(closestCenter(reorderArgs), sortableIds);
      }

      // Catalog / new-row: prefer layout slots, then section bodies, then end zone.
      // Never fall back to closestCenter over section headers — sticky first-section
      // headers steal drops meant for later sections (e.g. Bank Details).
      const slotHits = detectLayoutSlotCollisions(args);
      if (slotHits.length > 0) {
        return slotHits;
      }

      const sectionContainers = args.droppableContainers.filter((c) =>
        isSectionDroppableId(String(c.id)),
      );
      if (sectionContainers.length > 0) {
        const sectionArgs = { ...args, droppableContainers: sectionContainers };
        const sectionPointer = pointerWithin(sectionArgs);
        if (sectionPointer.length > 0) {
          return sectionPointer;
        }
        const sectionRect = rectIntersection(sectionArgs);
        if (sectionRect.length > 0) {
          return sectionRect;
        }
      }

      const endContainers = args.droppableContainers.filter(
        (c) => String(c.id) === E_APPROVAL_CANVAS_DROP_END_ID,
      );
      if (endContainers.length > 0) {
        const endArgs = { ...args, droppableContainers: endContainers };
        const endPointer = pointerWithin(endArgs);
        if (endPointer.length > 0) {
          return endPointer;
        }
      }

      const catalogSafeContainers = args.droppableContainers.filter((c) => {
        const id = String(c.id);
        return (
          isLayoutRowSlotDroppableId(id) ||
          isSectionDroppableId(id) ||
          id === E_APPROVAL_CANVAS_DROP_END_ID
        );
      });
      if (catalogSafeContainers.length > 0) {
        return closestCenter({ ...args, droppableContainers: catalogSafeContainers });
      }

      return [];
    },
    [sortableIds],
  );

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const replaceFields = (next: EApprovalFormFieldInput[], indexBump?: { fromIndex: number; delta: number }) => {
    const normalized = normalizeFormFieldLayouts(next, layoutRows);
    if (indexBump && indexBump.delta !== 0) {
      onLayoutRowsChange(bumpBuilderLayoutRowInsertIndices(layoutRows, indexBump.fromIndex, indexBump.delta));
    }
    onFieldsChange(normalized.map((f, i) => ({ ...f, step_order: i + 1 })));
  };

  const fieldsRef = useRef(fields);
  fieldsRef.current = fields;
  const layoutRowsSignature = useMemo(
    () => layoutRows.map((row) => `${row.id}:${row.columns}:${row.insert_index}`).join("|"),
    [layoutRows],
  );

  // Re-normalize field slot metadata when layout row scaffolds change — not on every fields update
  // (replaceFields already normalizes), to avoid Maximum update depth loops.
  useEffect(() => {
    const current = fieldsRef.current;
    const normalized = normalizeFormFieldLayouts(current, layoutRows);
    if (!fieldLayoutsChanged(current, normalized)) {
      return;
    }
    const verified = normalizeFormFieldLayouts(normalized, layoutRows);
    const next = fieldLayoutsChanged(normalized, verified) ? verified : normalized;
    onFieldsChange(next.map((f, i) => ({ ...f, step_order: i + 1 })));
    // layoutRowsSignature captures scaffold identity; layoutRows used for normalize payload
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [layoutRowsSignature, onFieldsChange]);

  const layoutRowColumnCount = (rowId: string, entries: EApprovalFieldListEntry[]) => {
    const scaffold = layoutRows.find((r) => r.id === rowId);
    if (scaffold) {
      return scaffold.columns;
    }
    if (entries.length > 0) {
      return inferLayoutRowColumnCount(entries);
    }
    return 2;
  };

  const entriesForLayoutRow = (rowId: string, source: EApprovalFormFieldInput[]): EApprovalFieldListEntry[] =>
    source
      .map((f, i) => ({ field: f, index: i }))
      .filter((e) => parseFieldLayout(e.field).row_id === rowId);

  const normalizeRowSlotTarget = (
    slotTarget: { rowId: string; slot: number },
    source: EApprovalFormFieldInput[],
  ): { rowId: string; slot: number } => {
    const columns = layoutRowColumnCount(slotTarget.rowId, entriesForLayoutRow(slotTarget.rowId, source));

    return { rowId: slotTarget.rowId, slot: clampLayoutRowSlot(slotTarget.slot, columns) };
  };

  const prepareFieldsForRowSlotDrop = (
    source: EApprovalFormFieldInput[],
    slotTarget: { rowId: string; slot: number },
  ): { fields: EApprovalFormFieldInput[]; slotTarget: { rowId: string; slot: number } } => {
    const normalized = normalizeRowSlotTarget(slotTarget, source);

    // Keep existing fields in the column — drops stack vertically in the same slot.
    return {
      fields: source,
      slotTarget: normalized,
    };
  };

  const buildFieldForCatalog = (catalogId: string, index: number): EApprovalFormFieldInput | null => {
    if (catalogId === "master-data") {
      return {
        ...defaultFieldForType("select", index),
        options: { master_data_key: "" },
      };
    }
    if (!catalogId) {
      return null;
    }
    return defaultFieldForType(catalogId as EApprovalFieldType, index);
  };

  const applyRowSlotLayout = (
    field: EApprovalFormFieldInput,
    slotTarget: { rowId: string; slot: number },
  ): EApprovalFormFieldInput => {
    const rowEntries: EApprovalFieldListEntry[] = fields
      .map((f, i) => ({ field: f, index: i }))
      .filter((e) => parseFieldLayout(e.field).row_id === slotTarget.rowId);
    const columnCount = layoutRowColumnCount(slotTarget.rowId, rowEntries);
    const slot = clampLayoutRowSlot(slotTarget.slot, columnCount);

    return {
      ...field,
      options: patchFieldLayout(field, {
        row_id: slotTarget.rowId,
        slot,
        width: layoutWidthForRowColumns(columnCount),
        row_columns: columnCount,
        stack_order: nextStackOrderForRowSlot(fields, slotTarget.rowId, slot),
      }),
    };
  };

  const insertCatalogBundle = (bundleId: EApprovalFormFieldBundleId, insertIndex: number) => {
    const bundleFields = buildFormFieldBundle(bundleId, insertIndex, collectFieldApiKeys(fields));
    if (!bundleFields || bundleFields.length === 0) {
      return;
    }

    const metadataPatch = getFormFieldBundleMetadataPatch(bundleId, insertIndex);
    if (metadataPatch && onMetadataPatch) {
      onMetadataPatch(metadataPatch);
    }

    const next = [...fields.slice(0, insertIndex), ...bundleFields, ...fields.slice(insertIndex)];
    replaceFields(next, { fromIndex: insertIndex, delta: bundleFields.length });
    setSelectedIndex(insertIndex);
  };

  const insertCatalogField = (
    catalogId: string,
    insertIndex: number,
    slotTarget?: { rowId: string; slot: number },
    options?: { bypassStepClamp?: boolean },
  ) => {
    if (!guardFileField(catalogId === "master-data" ? "select" : catalogId)) {
      return;
    }

    const prepared = slotTarget ? prepareFieldsForRowSlotDrop(fields, slotTarget) : null;
    const working = prepared?.fields ?? fields;
    const target = prepared?.slotTarget ?? slotTarget;
    const rawIndex = target
      ? findLayoutRowInsertIndex(working, target.rowId, target.slot, layoutRows)
      : insertIndex;
    const index = options?.bypassStepClamp
      ? Math.max(0, Math.min(rawIndex, working.length))
      : clampInsertIndexToActiveStep(rawIndex);

    const bundleId = parseFormFieldBundleCatalogId(catalogId);
    if (bundleId) {
      insertCatalogBundle(bundleId, index);
      return;
    }

    const draft = buildFieldForCatalog(catalogId, index);
    if (!draft) {
      return;
    }

    const taken = collectFieldApiKeys(working);
    const newField: EApprovalFormFieldInput = {
      ...draft,
      name: suggestApiKeyFromLabel(draft.label, taken),
    };

    const field = target ? applyRowSlotLayout(newField, target) : newField;
    const next = [...working.slice(0, index), field, ...working.slice(index)];
    replaceFields(next, { fromIndex: index, delta: 1 });
    setSelectedIndex(index);

    // New section/page-break boundaries should land on the step they create.
    if (steppedCanvas && (field.type === "section" || field.type === "page_break")) {
      const nextSteps = buildFormComposeSteps(next, composeSettings?.stepSource ?? "sections", {
        includeEmptySteps: true,
      });
      const stepIndex = nextSteps.findIndex((step) => step.fieldIndices.includes(index));
      if (stepIndex >= 0) {
        setActiveBuilderStepIndex(stepIndex);
      }
    }
  };

  const resolveDropInsertIndex = (overId: string | number): number => {
    const over = String(overId);

    const sectionGroupIndex = parseSectionDroppableId(over);
    if (sectionGroupIndex !== null) {
      const group = canvasDisplayGroups[sectionGroupIndex];
      if (group) {
        return clampInsertIndexToActiveStep(sectionGroupInsertIndex(group, fields.length));
      }
    }

    const slotTarget = parseLayoutRowSlotDroppableId(over);
    if (slotTarget) {
      return clampInsertIndexToActiveStep(
        findLayoutRowInsertIndex(fields, slotTarget.rowId, slotTarget.slot, layoutRows),
      );
    }

    return clampInsertIndexToActiveStep(
      resolveFieldInsertIndexFromCanvasTarget(over, fields, layoutRows, canvasDisplayGroups, sortableIds),
    );
  };

  /** Keep inserts inside the step currently shown on the stepped canvas. */
  const clampInsertIndexToActiveStep = (insertIndex: number): number => {
    if (!steppedCanvas || composeSteps.length === 0) {
      return insertIndex;
    }

    const step = composeSteps[activeBuilderStepIndex];
    if (!step) {
      return insertIndex;
    }

    if (step.fieldIndices.length === 0) {
      if (activeBuilderStepIndex <= 0) {
        return 0;
      }
      const previous = composeSteps[activeBuilderStepIndex - 1];
      if (!previous || previous.fieldIndices.length === 0) {
        return Math.max(0, Math.min(insertIndex, fields.length));
      }
      return Math.max(...previous.fieldIndices) + 1;
    }

    const min = Math.min(...step.fieldIndices);
    const max = Math.max(...step.fieldIndices);
    return Math.min(Math.max(insertIndex, min), max + 1);
  };

  const handleDragStart = (event: DragStartEvent) => {
    const id = String(event.active.id);
    if (isCatalogDragId(id) || parseCatalogRowDragId(id) !== null) {
      setActiveCatalogDragId(id);
      return;
    }
    if (isCanvasReorderDragId(id, sortableIds)) {
      setActiveCanvasReorderId(id);
    }
  };

  const handleDragOver = (event: DragOverEvent) => {
    const activeId = String(event.active.id);
    if (parseLayoutRowBlockDragId(activeId) !== null) {
      setDragOverSlot(null);
      return;
    }
    const parsed = parseLayoutRowSlotDroppableId(String(event.over?.id ?? ""));
    setDragOverSlot(parsed);
  };

  const moveFieldToRowSlot = (activeId: string, slotTarget: { rowId: string; slot: number }) => {
    const activeIndex = fields.findIndex((f, i) => canvasFieldSortableId(f, i) === activeId);
    if (activeIndex < 0 || !fieldSupportsLayout(fields[activeIndex]!.type)) {
      return false;
    }

    const current = fields[activeIndex]!;
    const without = fields.filter((_, i) => i !== activeIndex);
    const { fields: cleared, slotTarget: target } = prepareFieldsForRowSlotDrop(without, slotTarget);
    const patched = applyRowSlotLayout(current, target);
    const insertAt = findLayoutRowInsertIndex(cleared, target.rowId, target.slot, layoutRows);
    const next = [...cleared.slice(0, insertAt), patched, ...cleared.slice(insertAt)];
    replaceFields(next);
    setSelectedIndex(insertAt);
    return true;
  };

  const handleDragEnd = (event: DragEndEvent) => {
    setDragOverSlot(null);
    setActiveCatalogDragId(null);
    setActiveCanvasReorderId(null);
    const { active, over } = event;
    const activeId = String(active.id);

    if (!over) {
      if (isCanvasReorderDragId(activeId, sortableIds)) {
        const resolvedOver = pickReorderOverFromCollisions(event.collisions, sortableIds, activeId);
        if (resolvedOver && resolvedOver !== activeId) {
          reorderCanvas(activeId, resolvedOver);
        }
      }
      return;
    }

    const rowColumns = parseCatalogRowDragId(activeId);
    if (rowColumns) {
      const slotTarget = parseLayoutRowSlotDroppableId(String(over.id));
      const insertAt = slotTarget
        ? findLayoutRowInsertIndex(fields, slotTarget.rowId, slotTarget.slot, layoutRows)
        : resolveDropInsertIndex(over.id);
      addLayoutRow(rowColumns, insertAt);
      return;
    }

    const catalogId = parseCatalogFieldDragId(activeId);
    if (catalogId) {
      const slotTarget = parseLayoutRowSlotDroppableId(String(over.id));
      if (slotTarget) {
        insertCatalogField(catalogId, 0, slotTarget);
        return;
      }

      // Section / page-break on the end drop zone → append a new step after the whole form
      // (step clamp would otherwise bury it inside the active step and hide it).
      const overId = String(over.id);
      const opensNewStep =
        steppedCanvas &&
        overId === E_APPROVAL_CANVAS_DROP_END_ID &&
        (catalogId === "section" || catalogId === "page_break");
      if (opensNewStep) {
        insertCatalogField(catalogId, fields.length, undefined, { bypassStepClamp: true });
        return;
      }

      insertCatalogField(catalogId, resolveDropInsertIndex(over.id));
      return;
    }

    const overId = String(over.id);
    const slotTarget = parseLayoutRowSlotDroppableId(overId);
    if (slotTarget && parseLayoutRowBlockDragId(activeId) === null) {
      if (moveFieldToRowSlot(activeId, slotTarget)) {
        return;
      }
    }

    if (isCanvasReorderDragId(activeId, sortableIds)) {
      let resolvedOver =
        resolveCanvasReorderOverId(overId, sortableIds, activeId) ??
        pickReorderOverFromCollisions(event.collisions, sortableIds, activeId);

      if (resolvedOver && resolvedOver !== activeId) {
        reorderCanvas(activeId, resolvedOver);
        return;
      }
    }

    if (slotTarget) {
      moveFieldToRowSlot(activeId, slotTarget);
    }
  };

  const updateSelected = (patch: Partial<EApprovalFormFieldInput>) => {
    if (selectedIndex < 0 || !fields[selectedIndex]) {
      return;
    }

    const current = fields[selectedIndex]!;
    // Properties panel already merges option patches (including key removals such as
    // clearing master_data_key). Re-merging here would resurrect deleted keys.
    const merged: EApprovalFormFieldInput = { ...current, ...patch };
    if ("options" in patch) {
      merged.options = patch.options;
    }

    const next = [...fields];
    next[selectedIndex] = merged;
    replaceFields(next);
  };

  const canAddFileField = () => {
    if (plan.fileUploadsAllowed) {
      if (plan.maxFileFields === null) {
        return true;
      }
      const count = fields.filter((f) => f.type === "file").length;
      return count < plan.maxFileFields;
    }
    return false;
  };

  const guardFileField = (type: string): boolean => {
    if (type !== "file") {
      return true;
    }
    if (!plan.fileUploadsAllowed) {
      push({
        level: "warning",
        title: "File uploads not available",
        message: `Upgrade from ${plan.planTier} to Professional or Enterprise to add file fields. Open Billing & subscription for details.`,
      });
      return false;
    }
    if (!canAddFileField()) {
      push({
        level: "warning",
        title: "File field limit reached",
        message: `Your plan allows at most ${plan.maxFileFields} file field(s). See Administration → Billing.`,
      });
      return false;
    }
    return true;
  };

  const addLayoutRow = (columns: EApprovalLayoutRowColumns, insertIndex = fields.length) => {
    const rowId = createLayoutRowId();
    const clampedInsert = clampInsertIndexToActiveStep(
      Math.max(0, Math.min(insertIndex, fields.length)),
    );
    const nextRows = [
      ...layoutRows,
      { id: rowId, columns, insert_index: clampedInsert },
    ].sort((a, b) => a.insert_index - b.insert_index || a.id.localeCompare(b.id));
    onLayoutRowsChange(nextRows);
  };

  const removeLayoutRow = (rowId: string) => {
    onLayoutRowsChange(layoutRows.filter((row) => row.id !== rowId));
  };

  const updateLayoutRowColumns = (rowId: string, columns: EApprovalLayoutRowColumns) => {
    let nextRows = layoutRows.map((row) => (row.id === rowId ? { ...row, columns } : row));
    if (!nextRows.some((row) => row.id === rowId)) {
      const firstIdx = fields.findIndex((field) => parseFieldLayout(field).row_id === rowId);
      nextRows = [
        ...nextRows,
        { id: rowId, columns, insert_index: Math.max(0, firstIdx) },
      ].sort((a, b) => a.insert_index - b.insert_index || a.id.localeCompare(b.id));
    }
    onLayoutRowsChange(nextRows);

    const width = layoutWidthForRowColumns(columns);
    const patched = fields.map((field) => {
      const layout = parseFieldLayout(field);
      if (layout.row_id !== rowId) {
        return field;
      }
      return {
        ...field,
        options: patchFieldLayout(field, {
          row_columns: columns,
          width,
          slot:
            layout.slot !== undefined ? Math.min(Math.max(0, layout.slot), columns - 1) : layout.slot,
        }),
      };
    });
    onFieldsChange(
      normalizeFormFieldLayouts(patched, nextRows).map((field, index) => ({
        ...field,
        step_order: index + 1,
      })),
    );
  };

  const sectionLayoutInsertIndex = (group: (typeof canvasDisplayGroups)[number]): number =>
    sectionGroupInsertIndex(group, fields.length);

  const renderAddLayoutRowActions = (insertIndex: number, className?: string) => (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      <span className="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
        <Columns2 className="h-3.5 w-3.5" aria-hidden />
        Add row
      </span>
      {([2, 3, 4] as const).map((columns) => (
        <Button
          key={columns}
          type="button"
          size="sm"
          variant="outline"
          className="h-7 px-2 text-xs"
          onClick={() => addLayoutRow(columns, insertIndex)}
        >
          {columns} col
        </Button>
      ))}
    </div>
  );

  const reorderCanvas = (activeId: string, overId: string) => {
    const rowBlockActive = parseLayoutRowBlockDragId(activeId);
    let workingFields = fields;

    if (!rowBlockActive) {
      const activeFieldIndex = fields.findIndex((f, i) => canvasFieldSortableId(f, i) === activeId);
      if (activeFieldIndex >= 0) {
        const layout = parseFieldLayout(fields[activeFieldIndex]!);
        if (layout.row_id) {
          const overSlot = parseLayoutRowSlotDroppableId(overId);
          const overRowBlock = parseLayoutRowBlockDragId(overId);
          const stayingInRow =
            overSlot?.rowId === layout.row_id || overRowBlock === layout.row_id;
          if (!stayingInRow) {
            workingFields = fields.map((f, i) =>
              i === activeFieldIndex ? detachFieldFromRowLayout(f) : f,
            );
          }
        }
      }
    }

    const oldIndex = sortableIds.indexOf(activeId);
    let newIndex = sortableIds.indexOf(overId);
    if (oldIndex < 0) {
      return;
    }
    if (overId === E_APPROVAL_CANVAS_DROP_END_ID) {
      newIndex = Math.max(0, sortableIds.length - 1);
    } else if (newIndex < 0) {
      return;
    }
    if (oldIndex === newIndex) {
      return;
    }
    const nextOrder = arrayMove(sortableIds, oldIndex, newIndex);
    const nextFields = flattenSortableOrderToFields(nextOrder, workingFields);
    const nextLayoutRows = applySortableOrderToLayoutRows(nextOrder, workingFields, layoutRows);
    onLayoutRowsChange(nextLayoutRows);
    replaceFields(nextFields);
    const rowId = parseLayoutRowBlockDragId(activeId);
    if (rowId) {
      const first = nextFields.findIndex((f) => parseFieldLayout(f).row_id === rowId);
      if (first >= 0) {
        setSelectedIndex(first);
      }
      return;
    }
    const idx = nextFields.findIndex((f, i) => canvasFieldSortableId(f, i) === activeId);
    if (idx >= 0) {
      setSelectedIndex(idx);
    }
  };

  const duplicateField = (index: number) => {
    const source = fields[index];
    if (!source) {
      return;
    }
    const taken = collectFieldApiKeys(fields);
    const label = `${source.label} copy`.trim();
    const clone: EApprovalFormFieldInput = {
      ...source,
      id: undefined,
      label,
      name: suggestApiKeyFromLabel(label, taken),
      step_order: fields.length + 1,
    };
    const insertAt = index + 1;
    const next = [...fields.slice(0, insertAt), clone, ...fields.slice(insertAt)];
    replaceFields(next, { fromIndex: insertAt, delta: 1 });
    setSelectedIndex(insertAt);
  };

  const toggleGroupCollapsed = useCallback((groupIndex: number) => {
    setCollapsedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(groupIndex)) {
        next.delete(groupIndex);
      } else {
        next.add(groupIndex);
      }
      return next;
    });
  }, []);

  const expandAllCanvasGroups = useCallback(() => {
    setCollapsedGroups(new Set());
  }, []);

  const collapseAllCanvasGroups = useCallback(() => {
    setCollapsedGroups(new Set(canvasOutline.map((entry) => entry.groupIndex)));
  }, [canvasOutline]);

  const jumpToCanvasGroup = useCallback(
    (groupIndex: number) => {
      if (steppedCanvas && composeSteps.length >= 2) {
        setActiveBuilderStepIndex(groupIndex);
        setActiveOutlineGroup(groupIndex);
        setCollapsedGroups(new Set());
        window.requestAnimationFrame(() => {
          document.getElementById(builderCanvasSectionAnchorId(0))?.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        });
        return;
      }

      setCollapsedGroups((prev) => {
        if (!prev.has(groupIndex)) {
          return prev;
        }
        const next = new Set(prev);
        next.delete(groupIndex);
        return next;
      });
      setActiveOutlineGroup(groupIndex);
      window.requestAnimationFrame(() => {
        document.getElementById(builderCanvasSectionAnchorId(groupIndex))?.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      });
    },
    [composeSteps.length, steppedCanvas],
  );

  const jumpToField = useCallback(
    (fieldIndex: number) => {
      if (steppedCanvas && composeSteps.length > 0) {
        const stepIndex = composeSteps.findIndex((step) => step.fieldIndices.includes(fieldIndex));
        if (stepIndex >= 0) {
          setActiveBuilderStepIndex(stepIndex);
        }
      }

      const groupIndex = findDisplayGroupIndexForField(displayGroups, fieldIndex);
      if (groupIndex < 0) {
        return;
      }

      if (largeFormMode && !steppedCanvas) {
        setCollapsedGroups(
          new Set(displayGroups.map((_, index) => index).filter((index) => index !== groupIndex)),
        );
      } else {
        setCollapsedGroups((prev) => {
          if (!prev.has(groupIndex)) {
            return prev;
          }
          const next = new Set(prev);
          next.delete(groupIndex);
          return next;
        });
      }

      setActiveOutlineGroup(groupIndex);
      setSelectedIndex(fieldIndex);

      window.requestAnimationFrame(() => {
        document.getElementById(builderCanvasFieldAnchorId(fieldIndex))?.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      });
    },
    [composeSteps, displayGroups, fields, largeFormMode, steppedCanvas],
  );

  useEffect(() => {
    if (!showCanvasOutline || fields.length === 0) {
      return;
    }

    const groupIndex = displayGroups.findIndex((group) => {
      if (group.header?.index === selectedIndex) {
        return true;
      }
      return group.items.some((item) => item.index === selectedIndex);
    });

    if (groupIndex >= 0) {
      setActiveOutlineGroup(groupIndex);
    }
  }, [displayGroups, fields.length, selectedIndex, showCanvasOutline]);

  const renderSectionCollapseButton = (groupIndex: number, label: string) => {
    const collapsed = collapsedGroups.has(groupIndex);

    return (
      <button
        type="button"
        className="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted/60 hover:text-foreground"
        onClick={() => toggleGroupCollapsed(groupIndex)}
        aria-expanded={!collapsed}
        aria-label={collapsed ? `Expand ${label}` : `Collapse ${label}`}
      >
        <ChevronDown className={cn("h-4 w-4 transition-transform", collapsed && "-rotate-90")} />
      </button>
    );
  };

  const renderEmptyLayoutRow = (row: EApprovalBuilderLayoutRowScaffold) => (
    <SortableLayoutRowBlock
      key={`empty-row-${row.id}`}
      rowId={row.id}
      columnCount={row.columns}
      activeDragOverSlot={dragOverSlot?.rowId === row.id ? (dragOverSlot?.slot ?? null) : null}
      slots={Array.from({ length: row.columns }, () => null)}
      onRemoveEmptyRow={() => removeLayoutRow(row.id)}
      onColumnCountChange={(columns) => updateLayoutRowColumns(row.id, columns)}
      disableSlotDrops={isRowBlockReorderDragging}
    />
  );

  const renderCanvasSegment = (segment: EApprovalBuilderCanvasSegment, key: string) => {
    if (segment.kind === "empty-row") {
      return <div key={key}>{renderEmptyLayoutRow(segment.row)}</div>;
    }

    const node = segment.node;
    if (node.kind === "field") {
      return <div key={key}>{renderFieldRow(node.entry.index)}</div>;
    }

    const columnCount = layoutRowColumnCount(
      node.rowId,
      node.entries,
    );
    const slotsByColumn = assignEntriesToRowSlots(node.entries, columnCount);
    const slots = slotsByColumn.map((slotEntries, slot) => {
      if (slotEntries.length === 0) {
        return null;
      }
      return (
        <div key={slot} className="min-w-0 space-y-2">
          {slotEntries.map((e) => renderFieldRow(e.index))}
        </div>
      );
    });

    return (
      <SortableLayoutRowBlock
        key={key}
        rowId={node.rowId}
        columnCount={columnCount}
        activeDragOverSlot={dragOverSlot?.rowId === node.rowId ? dragOverSlot.slot : null}
        slots={slots}
        onColumnCountChange={(columns) => updateLayoutRowColumns(node.rowId, columns)}
        disableSlotDrops={isRowBlockReorderDragging}
      />
    );
  };

  const renderFieldRow = (index: number) => {
    const field = fields[index];
    if (!field) {
      return null;
    }

    const common = {
      field,
      selected: index === selectedIndex,
      onSelect: () => {
        setSelectedIndex(index);
        if (typeof window !== "undefined" && window.matchMedia("(max-width: 1279px)").matches) {
          setPropertiesOpen(true);
        }
      },
      onRemove: () => {
        const next = fields.filter((_, i) => i !== index);
        replaceFields(next, { fromIndex: index + 1, delta: -1 });
        setSelectedIndex(Math.max(0, index - 1));
      },
      onDuplicate: () => duplicateField(index),
      onMoveUp: () => {
        const layout = parseFieldLayout(field);
        if (layout.row_id) {
          const moved = moveFieldInRowSlot(fields, index, -1);
          if (!moved) {
            return;
          }
          replaceFields(moved.fields);
          setSelectedIndex(moved.selectedIndex);
          return;
        }
        const id = fieldKey(field, index);
        const pos = sortableIds.indexOf(id);
        if (pos <= 0) {
          return;
        }
        reorderCanvas(id, sortableIds[pos - 1]!);
      },
      onMoveDown: () => {
        const layout = parseFieldLayout(field);
        if (layout.row_id) {
          const moved = moveFieldInRowSlot(fields, index, 1);
          if (!moved) {
            return;
          }
          replaceFields(moved.fields);
          setSelectedIndex(moved.selectedIndex);
          return;
        }
        const id = fieldKey(field, index);
        const pos = sortableIds.indexOf(id);
        if (pos < 0 || pos >= sortableIds.length - 1) {
          return;
        }
        reorderCanvas(id, sortableIds[pos + 1]!);
      },
    };

    return <SortableFieldRow key={fieldKey(field, index)} index={index} {...common} />;
  };

  const catalogOverlayPick: EApprovalCatalogPick | null = (() => {
    if (!activeCatalogDragId) {
      return null;
    }
    const rowColumns = parseCatalogRowDragId(activeCatalogDragId);
    if (rowColumns) {
      return { kind: "layout-row", columns: rowColumns };
    }
    const catalogId = parseCatalogFieldDragId(activeCatalogDragId);
    if (!catalogId) {
      return null;
    }
    if (catalogId === "master-data") {
      return { kind: "master-data" };
    }
    const bundleId = parseFormFieldBundleCatalogId(catalogId);
    if (bundleId) {
      return { kind: "bundle", bundle: bundleId };
    }
    return { kind: "field", type: catalogId as EApprovalFieldType };
  })();
  const catalogOverlayLabel = catalogOverlayPick ? catalogPickLabel(catalogOverlayPick) : null;
  const CatalogOverlayIcon = catalogOverlayPick ? catalogPickIcon(catalogOverlayPick) : null;

  const propertiesPanel = selectedField ? (
    <EApprovalFieldPropertiesPanel
      field={selectedField}
      allFields={fields}
      fieldIndex={selectedIndex}
      layoutRows={layoutRows}
      apiKeysLocked={apiKeysLocked}
      onChange={updateSelected}
    />
  ) : (
    <p className="text-sm text-muted-foreground">Select a field to edit properties.</p>
  );

  return (
    <div className="space-y-4">
      <DndContext
        sensors={sensors}
        collisionDetection={collisionDetection}
        onDragStart={handleDragStart}
        onDragOver={handleDragOver}
        onDragEnd={handleDragEnd}
      >
        <div className="flex min-h-0 flex-col gap-4 xl:flex-row xl:items-stretch">
          <EApprovalFieldCatalogPalette className="h-[min(820px,calc(100vh-8rem))] w-full shrink-0 xl:w-52 2xl:w-56" />

          <div className="min-w-0 flex-1">
            <section className="space-y-3 rounded-xl border border-border bg-card p-3 shadow-sm sm:p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                  <h2 className="text-base font-medium">Form canvas</h2>
                  {composeSummary ? (
                    <Badge
                      variant="outline"
                      className={cn(
                        "font-normal",
                        composeSummary.steppedActive && "border-primary/30 text-primary",
                        !composeSummary.ready && "border-amber-300 text-amber-900 dark:text-amber-100",
                      )}
                    >
                      {composeSummary.modeLabel}
                      {composeSummary.fillableFieldCount > 0
                        ? ` · ${composeSummary.fillableFieldCount} fields`
                        : ""}
                    </Badge>
                  ) : null}
                  {largeFormMode ? (
                    <Badge variant="secondary" className="font-normal">
                      Large form
                    </Badge>
                  ) : null}
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="xl:hidden"
                  onClick={() => setPropertiesOpen(true)}
                  disabled={!selectedField}
                >
                  <Settings2 className="mr-1 h-4 w-4" />
                  Properties
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                Drag fields from the catalog onto the canvas or row slots. Use <span className="font-medium">Add row</span>{" "}
                in a section to create 2/3/4-column layouts (or drag row layouts from the catalog). Drag the{" "}
                <GripVertical className="inline h-3 w-3 align-text-bottom" /> handle on a column row header to move the
                whole row up or down. Change columns with the row&apos;s col selector. On touch devices, press and hold
                before dragging.
                {showCanvasOutline ? " Use the outline to jump between sections." : null}
                {steppedCanvas ? " Edit one requestor step at a time using the step tabs below." : null}
                {largeFormMode && !steppedCanvas
                  ? " Sections start collapsed for performance — use search or the outline to jump to a field."
                  : null}
              </p>

              {showFieldSearch ? (
                <EApprovalBuilderFieldSearch
                  entries={fieldSearchIndex}
                  onSelect={(entry) => jumpToField(entry.index)}
                />
              ) : null}

              {steppedCanvas && composeSteps.length >= 2 ? (
                <EApprovalComposeStepNav
                  steps={composeSteps}
                  currentStep={activeBuilderStepIndex}
                  allowStepSelect
                  allowAnyStep
                  onStepSelect={setActiveBuilderStepIndex}
                />
              ) : null}

              {showCanvasOutline ? (
                <div className="md:hidden">
                  <div className="flex gap-1.5 overflow-x-auto pb-1">
                    {canvasOutline.map((entry) => (
                      <button
                        key={`outline-${entry.groupIndex}-${entry.anchorId}`}
                        type="button"
                        className={cn(
                          "shrink-0 rounded-md border px-2.5 py-1 text-left text-[11px] transition-colors",
                          activeOutlineGroup === entry.groupIndex
                            ? "border-primary/40 bg-primary/5 text-primary"
                            : "border-border bg-background text-muted-foreground",
                        )}
                        onClick={() => jumpToCanvasGroup(entry.groupIndex)}
                      >
                        <span className="font-medium text-foreground">{entry.label}</span>
                        <span className="ml-1 tabular-nums text-muted-foreground">({entry.fieldCount})</span>
                      </button>
                    ))}
                  </div>
                </div>
              ) : null}

              <div
                className={cn(
                  "min-h-0",
                  showCanvasOutline && "flex gap-3",
                )}
              >
                {showCanvasOutline ? (
                  <EApprovalBuilderCanvasOutline
                    entries={canvasOutline}
                    collapsedGroups={collapsedGroups}
                    activeGroupIndex={activeOutlineGroup}
                    onJumpToGroup={jumpToCanvasGroup}
                    onToggleGroup={toggleGroupCollapsed}
                    onExpandAll={expandAllCanvasGroups}
                    onCollapseAll={collapseAllCanvasGroups}
                    className="hidden w-40 shrink-0 md:flex 2xl:w-44"
                  />
                ) : null}

                <div
                  ref={canvasScrollRef}
                  className={cn(
                    "min-w-0 flex-1",
                    (showCanvasOutline || largeFormMode) &&
                      "max-h-[min(820px,calc(100vh-10rem))] overflow-y-auto overscroll-y-contain pr-1",
                  )}
                >
              <SortableContext items={sortableIds} strategy={verticalListSortingStrategy}>
                <div className={E_APPROVAL_COMPOSE_SHELL_CLASS}>
                  {fields.length === 0 ? (
                    <>
                      {layoutRows.map((row) => renderEmptyLayoutRow(row))}
                      {renderAddLayoutRowActions(0, "justify-center")}
                      <CanvasDropZone active={isCatalogDragging} className="min-h-[120px] py-8" />
                    </>
                  ) : (
                    canvasDisplayGroups.map((group, gi) => {
                      const collapsed = collapsedGroups.has(gi);
                      const outlineEntry = steppedCanvas
                        ? canvasOutline.find((entry) => entry.groupIndex === activeBuilderStepIndex)
                        : canvasOutline.find((entry) => entry.groupIndex === gi);
                      const sectionLabel = outlineEntry?.label ?? `Section ${gi + 1}`;

                      return (
                      <div
                        key={gi}
                        id={builderCanvasSectionAnchorId(gi)}
                        className={cn(
                          "scroll-mt-3",
                          group.header && "overflow-hidden rounded-xl border border-border bg-card",
                        )}
                      >
                        {group.header ? (
                          <div
                            className={cn(
                              "flex items-start gap-0.5 border-b border-border bg-muted/40 px-1 py-1",
                              largeFormMode &&
                                "sticky top-0 z-10 border-b border-border bg-card/95 backdrop-blur-sm",
                              // Sticky first-section headers must not steal catalog drops aimed at later sections.
                              isCatalogDragging && largeFormMode && "pointer-events-none",
                            )}
                          >
                            {showCanvasOutline
                              ? renderSectionCollapseButton(gi, sectionLabel)
                              : null}
                            <div className="min-w-0 flex-1">{renderFieldRow(group.header.index)}</div>
                          </div>
                        ) : showCanvasOutline && group.items.length > 0 ? (
                          <div className="mb-2 flex items-center gap-1 px-1">
                            {renderSectionCollapseButton(gi, sectionLabel)}
                            <p className="text-xs font-medium text-muted-foreground">{sectionLabel}</p>
                          </div>
                        ) : null}
                        {!collapsed ? (
                        <SectionBodyDropZone
                          groupIndex={gi}
                          active={isCatalogDragging}
                          className={cn(
                            "space-y-3",
                            group.header ? "ml-3 border-l-2 border-border px-2 py-3" : "",
                          )}
                        >
                          {buildBuilderGroupSegments(
                            group.items,
                            layoutRows,
                            group.header?.index ?? null,
                            canvasDisplayGroups[gi + 1]?.header?.index ?? null,
                          ).map((segment, si) => renderCanvasSegment(segment, `g${gi}-s${si}`))}
                          {renderAddLayoutRowActions(sectionLayoutInsertIndex(group))}
                        </SectionBodyDropZone>
                        ) : (
                          <p className="px-3 py-2 text-xs text-muted-foreground">
                            {group.items.length} field{group.items.length === 1 ? "" : "s"} collapsed — expand from
                            the outline or section header.
                          </p>
                        )}
                      </div>
                    );
                    })
                  )}
                  {fields.length > 0 ? <CanvasDropZone active={isCatalogDragging} /> : null}
                </div>
              </SortableContext>
                </div>
              </div>
            </section>
          </div>

          <aside className="hidden h-[min(820px,calc(100vh-8rem))] w-full min-w-0 shrink-0 xl:block xl:w-64 2xl:w-72">
            <section className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="shrink-0 text-base font-medium">Field properties</h2>
              <div className="mt-3 min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain pr-1">
                {propertiesPanel}
              </div>
            </section>
          </aside>
        </div>

        <DragOverlay dropAnimation={null}>
          {catalogOverlayLabel && CatalogOverlayIcon ? (
            <div className="flex items-center gap-2 rounded-lg border border-primary bg-card px-3 py-2 text-sm shadow-lg">
              <CatalogOverlayIcon className="h-4 w-4 text-primary" />
              <span className="font-medium">{catalogOverlayLabel}</span>
            </div>
          ) : null}
        </DragOverlay>
      </DndContext>

      <Dialog open={propertiesOpen} onOpenChange={setPropertiesOpen}>
        <DialogContent className="max-h-[min(90vh,720px)] w-[min(calc(100vw-1rem),480px)]">
          <DialogHeader>
            <DialogTitle>Field properties</DialogTitle>
          </DialogHeader>
          <DialogBody className="min-h-0 overflow-y-auto">{propertiesPanel}</DialogBody>
        </DialogContent>
      </Dialog>
    </div>
  );
}
