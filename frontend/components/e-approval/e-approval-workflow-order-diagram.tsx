"use client";

import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  type DragEndEvent,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ChevronDown, ChevronUp, GripVertical, Trash2 } from "lucide-react";

import {
  EApprovalWorkflowAddMenu,
  type WorkflowAddKind,
} from "@/components/e-approval/e-approval-workflow-add-menu";
import {
  WorkflowDiagramConnector,
  WorkflowDiagramNodeCard,
  WorkflowDiagramShell,
  WorkflowDiagramTerminal,
} from "@/components/e-approval/e-approval-workflow-diagram-chrome";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  buildWorkflowEditorDiagram,
  moveWorkflowVisualBand,
  removeWorkflowVisualBandAt,
  reorderWorkflowVisualBands,
  type WorkflowEditorDiagramBand,
  type WorkflowEditorDiagramNode,
} from "@/modules/e-approval/workflow-editor-diagram";

type Props = {
  steps: EApprovalWorkflowStepInput[];
  fields: EApprovalFormFieldInput[];
  onStepsChange: (steps: EApprovalWorkflowStepInput[]) => void;
  titleForStep: (step: EApprovalWorkflowStepInput, index: number) => string;
  subtitleForStep?: (step: EApprovalWorkflowStepInput, index: number) => string;
  selectedStepIndex: number | null;
  onSelectStep: (index: number) => void;
  /** Insert step or routing at array index (end of prior band / start of next). */
  onInsert: (kind: WorkflowAddKind, insertAt: number) => void;
  className?: string;
};

function InsertPoint({
  onInsert,
  ariaLabel,
}: {
  onInsert: (kind: WorkflowAddKind) => void;
  ariaLabel: string;
}) {
  return (
    <div className="relative z-20 flex w-full flex-col items-center overflow-visible py-1">
      <WorkflowDiagramConnector />
      <div className="absolute top-1/2 left-1/2 z-30 -translate-x-1/2 -translate-y-1/2 overflow-visible">
        <EApprovalWorkflowAddMenu
          iconOnly
          size="icon-sm"
          variant="outline"
          align="center"
          side="top"
          ariaLabel={ariaLabel}
          onSelect={onInsert}
          className="overflow-visible bg-card"
          triggerClassName="size-7 rounded-full bg-card shadow-sm"
        />
      </div>
    </div>
  );
}

function SortableBand({
  band,
  bandIndex,
  bandCount,
  selectedStepIndex,
  onSelectStep,
  onMove,
  onInsertAfter,
  onRemove,
}: {
  band: WorkflowEditorDiagramBand;
  bandIndex: number;
  bandCount: number;
  selectedStepIndex: number | null;
  onSelectStep: (index: number) => void;
  onMove: (direction: "up" | "down") => void;
  onInsertAfter: (kind: WorkflowAddKind) => void;
  onRemove: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: band.id,
  });
  const grouped = band.variant === "parallel" || band.variant === "exclusive";
  const labelTone =
    band.variant === "exclusive"
      ? "text-sky-800 dark:text-sky-200"
      : "text-violet-800 dark:text-violet-200";
  const groupShell =
    band.variant === "exclusive"
      ? "rounded-xl border border-sky-200/80 bg-sky-50/40 p-2 dark:border-sky-900/50 dark:bg-sky-950/20"
      : band.variant === "parallel"
        ? "rounded-xl border border-violet-200/80 bg-violet-50/40 p-2 dark:border-violet-900/50 dark:bg-violet-950/20"
        : "";

  return (
    <div
      ref={setNodeRef}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
      }}
      className={cn("w-full", isDragging && "z-10 opacity-90")}
    >
      {band.bandLabel ? (
        <p className={cn("mb-2 text-center text-xs font-medium", labelTone)}>
          Steps {band.orderLabel} — {band.bandLabel}
        </p>
      ) : null}
      <div
        className={cn(
          "flex w-full justify-center",
          isDragging && "rounded-xl ring-2 ring-sky-400/50",
        )}
      >
        <div className="flex max-w-full items-stretch gap-2">
        <button
          type="button"
          className="mt-1 flex shrink-0 cursor-grab items-start rounded-md px-1 py-2 text-muted-foreground hover:bg-muted/60 active:cursor-grabbing"
          aria-label={`Drag band ${band.orderLabel}`}
          {...attributes}
          {...listeners}
        >
          <GripVertical className="h-4 w-4" />
        </button>

        <div
          className={cn(
            "flex min-w-0 flex-wrap items-stretch justify-center gap-2",
            grouped ? "w-full max-w-5xl" : "w-auto",
            grouped && groupShell,
          )}
        >
          {band.nodes.map((node: WorkflowEditorDiagramNode) => (
            <div
              key={node.id}
              className={cn(
                "flex flex-col gap-1",
                grouped ? "min-w-[13rem] max-w-[24rem] flex-1" : "w-[min(20rem,calc(100vw-8rem))]",
              )}
            >
              {node.caseLabel ? (
                <p className="px-1 text-center text-[11px] font-medium text-sky-900 dark:text-sky-200">
                  {node.caseLabel}
                </p>
              ) : null}
              <WorkflowDiagramNodeCard
                node={node}
                wide={grouped}
                selected={selectedStepIndex === node.stepIndex}
                onSelect={() => onSelectStep(node.stepIndex)}
                statusFallback={
                  node.warning ? (
                    <span className="text-xs text-amber-700 dark:text-amber-300">Incomplete</span>
                  ) : (
                    <span className="text-xs text-muted-foreground">Configured</span>
                  )
                }
              />
            </div>
          ))}
        </div>

        <div className="flex shrink-0 flex-col items-center justify-center gap-0.5">
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            disabled={bandIndex === 0}
            aria-label={`Move band ${band.orderLabel} up`}
            onClick={() => onMove("up")}
          >
            <ChevronUp className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            disabled={bandIndex >= bandCount - 1}
            aria-label={`Move band ${band.orderLabel} down`}
            onClick={() => onMove("down")}
          >
            <ChevronDown className="h-4 w-4" />
          </Button>
          <EApprovalWorkflowAddMenu
            iconOnly
            size="icon-sm"
            variant="ghost"
            align="end"
            ariaLabel={`Add after band ${band.orderLabel}`}
            onSelect={onInsertAfter}
          />
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            className="text-muted-foreground hover:text-destructive"
            aria-label={
              band.variant === "exclusive"
                ? `Remove ${band.bandLabel ?? "exclusive branch"}`
                : band.variant === "parallel"
                  ? `Remove parallel step ${band.orderLabel}`
                  : `Remove step ${band.orderLabel}`
            }
            onClick={onRemove}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
        </div>
      </div>
    </div>
  );
}

/** Path-style create canvas: Start → bands → End with band reorder and click-to-edit. */
export function EApprovalWorkflowOrderDiagram({
  steps,
  fields,
  onStepsChange,
  titleForStep,
  subtitleForStep,
  selectedStepIndex,
  onSelectStep,
  onInsert,
  className,
}: Props) {
  const bands = buildWorkflowEditorDiagram(steps, fields, {
    titleForStep,
    subtitleForStep,
  });
  const bandIds = bands.map((band) => band.id);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) {
      return;
    }
    const fromIndex = bandIds.indexOf(String(active.id));
    const toIndex = bandIds.indexOf(String(over.id));
    if (fromIndex < 0 || toIndex < 0) {
      return;
    }
    onStepsChange(reorderWorkflowVisualBands(steps, bands, fromIndex, toIndex));
  };

  const insertAtAfterBand = (bandIndex: number): number => {
    const band = bands[bandIndex];
    if (!band) {
      return steps.length;
    }
    return Math.max(...band.memberIndexes) + 1;
  };

  if (bands.length === 0) {
    return null;
  }

  return (
    <WorkflowDiagramShell
      title="Workflow path"
      description="Click a step to configure it in the side panel. If/Else and ladders are side-by-side (one case runs). Drag, +, or trash act on the whole band."
      className={className}
      actions={
        <EApprovalWorkflowAddMenu
          label="Add to path"
          variant="secondary"
          onSelect={(kind) => onInsert(kind, steps.length)}
        />
      }
      legend={
        <div className="flex flex-wrap gap-3 text-[11px] text-muted-foreground">
          <span className="inline-flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full bg-violet-500" /> Parallel
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full bg-sky-500" /> If/Else · Ladder
          </span>
          <span>
            {bands.length} band{bands.length === 1 ? "" : "s"}
          </span>
        </div>
      }
    >
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={bandIds} strategy={verticalListSortingStrategy}>
          <WorkflowDiagramTerminal label="Start" />
          {bands.map((band, bandIndex) => (
            <div key={band.id} className="flex w-full flex-col items-center">
              <InsertPoint
                ariaLabel={
                  bandIndex === 0
                    ? "Insert at start of path"
                    : `Insert before band ${band.orderLabel}`
                }
                onInsert={(kind) =>
                  onInsert(
                    kind,
                    bandIndex === 0 ? 0 : insertAtAfterBand(bandIndex - 1),
                  )
                }
              />
              <SortableBand
                band={band}
                bandIndex={bandIndex}
                bandCount={bands.length}
                selectedStepIndex={selectedStepIndex}
                onSelectStep={onSelectStep}
                onMove={(direction) =>
                  onStepsChange(moveWorkflowVisualBand(steps, bands, bandIndex, direction))
                }
                onInsertAfter={(kind) => onInsert(kind, insertAtAfterBand(bandIndex))}
                onRemove={() => onStepsChange(removeWorkflowVisualBandAt(steps, bands, bandIndex))}
              />
            </div>
          ))}
          <InsertPoint
            ariaLabel="Insert before end"
            onInsert={(kind) => onInsert(kind, steps.length)}
          />
          <WorkflowDiagramTerminal label="End" />
        </SortableContext>
      </DndContext>
    </WorkflowDiagramShell>
  );
}
