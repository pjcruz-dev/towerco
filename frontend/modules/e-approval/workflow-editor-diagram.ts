import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  branchGroupLabel,
  ladderGroupLabel,
  parseThresholdBand,
  thresholdBandLabel,
  toWorkflowEditorSegments,
} from "@/modules/e-approval/workflow-branch-groups";
import { compactWorkflowStepOrdersPreservingTies } from "@/modules/e-approval/workflow-parallel-groups";
import { stepRunsAlways, whenSummary } from "@/modules/e-approval/workflow-conditions";
import {
  describeParallelBandLabel,
  workflowStepTypeLabel,
  type WorkflowDiagramBand,
  type WorkflowDiagramNode,
} from "@/modules/e-approval/workflow-path-diagram";
import { getValidEApprovalWorkflowSteps } from "@/modules/e-approval/workflow-steps";

export type WorkflowEditorBandVariant = "single" | "parallel" | "exclusive";

export type WorkflowEditorDiagramNode = WorkflowDiagramNode & {
  stepIndex: number;
  /** Short case label for If/Else / ladder cards (e.g. Non-PO ≤ 5000). */
  caseLabel?: string | null;
};

export type WorkflowEditorDiagramBand = Omit<WorkflowDiagramBand, "nodes"> & {
  memberIndexes: number[];
  nodes: WorkflowEditorDiagramNode[];
  variant: WorkflowEditorBandVariant;
  /** Shown in the band header (e.g. "7" or "1–3"). */
  orderLabel: string;
};

export type WorkflowEditorDiagramResolve = {
  titleForStep: (step: EApprovalWorkflowStepInput, index: number) => string;
  subtitleForStep?: (step: EApprovalWorkflowStepInput, index: number) => string;
};

function isStepComplete(step: EApprovalWorkflowStepInput): boolean {
  return getValidEApprovalWorkflowSteps([step]).length === 1;
}

function fieldLabel(fields: EApprovalFormFieldInput[], fieldId: string): string {
  return fields.find((field) => field.name === fieldId)?.label?.trim() || fieldId;
}

function nodeFromStep(
  steps: EApprovalWorkflowStepInput[],
  fields: EApprovalFormFieldInput[],
  resolve: WorkflowEditorDiagramResolve,
  stepIndex: number,
  stepOrder: number,
  caseLabel?: string | null,
): WorkflowEditorDiagramNode {
  const step = steps[stepIndex];
  const title = resolve.titleForStep(step, stepIndex);
  const subtitle =
    resolve.subtitleForStep?.(step, stepIndex) ?? workflowStepTypeLabel(step.type);
  const detail = caseLabel
    ? `Case: ${caseLabel}`
    : stepRunsAlways(step)
      ? "Always runs for matching submissions."
      : `Runs when: ${whenSummary(step, fields)}`;
  const complete = isStepComplete(step);

  return {
    id: `editor-${step.id ?? stepIndex}-${stepOrder}`,
    stepOrder,
    title,
    subtitle,
    detail,
    kind: "run",
    status: null,
    warning: complete ? null : "Needs approver assignment",
    stepIndex,
    caseLabel: caseLabel ?? null,
  };
}

/** Build path-diagram bands; If/Else and ladders render side-by-side like parallel. */
export function buildWorkflowEditorDiagram(
  steps: EApprovalWorkflowStepInput[],
  fields: EApprovalFormFieldInput[],
  resolve: WorkflowEditorDiagramResolve,
): WorkflowEditorDiagramBand[] {
  const segments = toWorkflowEditorSegments(steps);

  return segments.map((segment, segmentIndex) => {
    if (segment.type === "parallel") {
      const { group } = segment;
      const members = group.memberIndexes.map((index) => steps[index]);
      const nodes = group.memberIndexes.map((stepIndex) =>
        nodeFromStep(steps, fields, resolve, stepIndex, group.stepOrder),
      );
      return {
        id: `visual-parallel-${group.stepOrder}-${segmentIndex}`,
        stepOrder: group.stepOrder,
        kind: "run" as const,
        variant: "parallel" as const,
        bandLabel: describeParallelBandLabel(members) ?? "Parallel",
        orderLabel: String(group.stepOrder),
        memberIndexes: [...group.memberIndexes].sort((a, b) => a - b),
        nodes,
      };
    }

    if (segment.type === "branch") {
      const { group } = segment;
      const labels = branchGroupLabel(group, fieldLabel(fields, group.field));
      const ordered = [group.lowIndex, group.highIndex];
      const orders = ordered.map((index) => steps[index].step_order ?? index + 1);
      const orderLabel = `${Math.min(...orders)}–${Math.max(...orders)}`;
      const caseLabels = [labels.ifLabel, labels.elseLabel];
      const nodes = ordered.map((stepIndex, offset) =>
        nodeFromStep(
          steps,
          fields,
          resolve,
          stepIndex,
          steps[stepIndex].step_order ?? stepIndex + 1,
          caseLabels[offset],
        ),
      );
      return {
        id: `visual-branch-${group.startIndex}`,
        stepOrder: Math.min(...orders),
        kind: "run" as const,
        variant: "exclusive" as const,
        bandLabel: `If / Else · ${fieldLabel(fields, group.field)}`,
        orderLabel,
        memberIndexes: ordered,
        nodes,
      };
    }

    if (segment.type === "ladder") {
      const { ladder } = segment;
      const header = ladderGroupLabel(ladder, fieldLabel(fields, ladder.field)).header;
      const orders = ladder.bandIndexes.map(
        (index) => steps[index].step_order ?? index + 1,
      );
      const orderLabel = `${Math.min(...orders)}–${Math.max(...orders)}`;
      const nodes = ladder.bandIndexes.map((stepIndex) => {
        const band = parseThresholdBand(steps[stepIndex]);
        const caseLabel = band
          ? thresholdBandLabel(band, fieldLabel(fields, ladder.field))
          : whenSummary(steps[stepIndex], fields);
        return nodeFromStep(
          steps,
          fields,
          resolve,
          stepIndex,
          steps[stepIndex].step_order ?? stepIndex + 1,
          caseLabel,
        );
      });
      return {
        id: `visual-ladder-${ladder.startIndex}`,
        stepOrder: Math.min(...orders),
        kind: "run" as const,
        variant: "exclusive" as const,
        bandLabel: header.includes("If / Else")
          ? `If / Else · ${fieldLabel(fields, ladder.field)}`
          : `Threshold ladder · ${fieldLabel(fields, ladder.field)}`,
        orderLabel,
        memberIndexes: [...ladder.bandIndexes],
        nodes,
      };
    }

    const stepIndex = segment.index;
    const stepOrder = steps[stepIndex].step_order ?? stepIndex + 1;
    return {
      id: `visual-single-${stepIndex}`,
      stepOrder,
      kind: "run" as const,
      variant: "single" as const,
      bandLabel: null,
      orderLabel: String(stepOrder),
      memberIndexes: [stepIndex],
      nodes: [nodeFromStep(steps, fields, resolve, stepIndex, stepOrder)],
    };
  });
}

/**
 * Reorder visual bands (single / parallel / exclusive fork) while keeping
 * parallel ties and exclusive case order inside each band.
 */
export function reorderWorkflowVisualBands(
  steps: EApprovalWorkflowStepInput[],
  bands: Pick<WorkflowEditorDiagramBand, "memberIndexes" | "variant">[],
  fromBandIndex: number,
  toBandIndex: number,
): EApprovalWorkflowStepInput[] {
  if (
    fromBandIndex === toBandIndex ||
    fromBandIndex < 0 ||
    toBandIndex < 0 ||
    fromBandIndex >= bands.length ||
    toBandIndex >= bands.length
  ) {
    return steps;
  }

  const reordered = [...bands];
  const [moved] = reordered.splice(fromBandIndex, 1);
  reordered.splice(toBandIndex, 0, moved);

  const next: EApprovalWorkflowStepInput[] = [];
  let order = 1;
  for (const band of reordered) {
    const members = [...band.memberIndexes].sort((a, b) => a - b);
    if (band.variant === "parallel") {
      for (const memberIndex of members) {
        next.push({ ...steps[memberIndex], step_order: order });
      }
      order += 1;
      continue;
    }
    for (const memberIndex of members) {
      next.push({ ...steps[memberIndex], step_order: order });
      order += 1;
    }
  }

  return compactWorkflowStepOrdersPreservingTies(next);
}

export function moveWorkflowVisualBand(
  steps: EApprovalWorkflowStepInput[],
  bands: Pick<WorkflowEditorDiagramBand, "memberIndexes" | "variant">[],
  bandIndex: number,
  direction: "up" | "down",
): EApprovalWorkflowStepInput[] {
  return reorderWorkflowVisualBands(
    steps,
    bands,
    bandIndex,
    bandIndex + (direction === "up" ? -1 : 1),
  );
}

/** Remove every step belonging to a visual band (entire If/Else, ladder, or parallel). */
export function removeWorkflowVisualBandAt(
  steps: EApprovalWorkflowStepInput[],
  bands: Pick<WorkflowEditorDiagramBand, "memberIndexes">[],
  bandIndex: number,
): EApprovalWorkflowStepInput[] {
  const band = bands[bandIndex];
  if (!band) {
    return steps;
  }
  const removeIndexes = new Set(band.memberIndexes);
  return compactWorkflowStepOrdersPreservingTies(
    steps.filter((_, index) => !removeIndexes.has(index)),
  );
}
