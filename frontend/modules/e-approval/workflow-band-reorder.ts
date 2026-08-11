import type { EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import { compactWorkflowStepOrdersPreservingTies } from "@/modules/e-approval/workflow-parallel-groups";
import { describeParallelBandLabel } from "@/modules/e-approval/workflow-path-diagram";

export type WorkflowEditorBand = {
  /** Stable id for DnD (based on member step ids / indexes). */
  id: string;
  stepOrder: number;
  memberIndexes: number[];
  bandLabel: string | null;
};

/** Group steps into order bands (parallel siblings share one band). */
export function groupWorkflowStepsIntoBands(
  steps: EApprovalWorkflowStepInput[],
): WorkflowEditorBand[] {
  if (steps.length === 0) {
    return [];
  }

  const byOrder = new Map<number, number[]>();
  steps.forEach((step, index) => {
    const order = step.step_order ?? index + 1;
    const members = byOrder.get(order) ?? [];
    members.push(index);
    byOrder.set(order, members);
  });

  return [...byOrder.entries()]
    .sort((a, b) => a[0] - b[0])
    .map(([stepOrder, memberIndexes]) => {
      const sorted = [...memberIndexes].sort((a, b) => a - b);
      const members = sorted.map((index) => steps[index]);
      return {
        id: `band-${sorted.map((index) => steps[index]?.id ?? `i${index}`).join("_")}`,
        stepOrder,
        memberIndexes: sorted,
        bandLabel: describeParallelBandLabel(members),
      };
    });
}

/**
 * Reorder bands by index. Parallel siblings stay together and keep a shared
 * step_order; orders are compacted to 1..N afterward.
 */
export function reorderWorkflowStepBands(
  steps: EApprovalWorkflowStepInput[],
  fromBandIndex: number,
  toBandIndex: number,
): EApprovalWorkflowStepInput[] {
  if (steps.length === 0 || fromBandIndex === toBandIndex) {
    return steps;
  }

  const bands = groupWorkflowStepsIntoBands(steps);
  if (
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
  reordered.forEach((band, bandIndex) => {
    const stepOrder = bandIndex + 1;
    for (const memberIndex of band.memberIndexes) {
      next.push({
        ...steps[memberIndex],
        step_order: stepOrder,
      });
    }
  });

  return compactWorkflowStepOrdersPreservingTies(next);
}

export function moveWorkflowStepBand(
  steps: EApprovalWorkflowStepInput[],
  bandIndex: number,
  direction: "up" | "down",
): EApprovalWorkflowStepInput[] {
  const delta = direction === "up" ? -1 : 1;
  return reorderWorkflowStepBands(steps, bandIndex, bandIndex + delta);
}

/** Remove every step in a band (parallel siblings removed together). */
export function removeWorkflowBandAt(
  steps: EApprovalWorkflowStepInput[],
  bandIndex: number,
): EApprovalWorkflowStepInput[] {
  const bands = groupWorkflowStepsIntoBands(steps);
  const band = bands[bandIndex];
  if (!band) {
    return steps;
  }
  const removeIndexes = new Set(band.memberIndexes);
  return compactWorkflowStepOrdersPreservingTies(
    steps.filter((_, index) => !removeIndexes.has(index)),
  );
}

/**
 * Insert a single step at an array index and renumber so it becomes its own
 * band between the surrounding bands (not appended as the last step_order).
 */
export function insertWorkflowStepAt(
  steps: EApprovalWorkflowStepInput[],
  step: EApprovalWorkflowStepInput,
  atIndex?: number,
): EApprovalWorkflowStepInput[] {
  const insertAt = Math.max(0, Math.min(atIndex ?? steps.length, steps.length));
  const before = compactWorkflowStepOrdersPreservingTies(steps.slice(0, insertAt));
  const afterSource = steps.slice(insertAt);
  const baseOrder =
    before.length > 0 ? Math.max(...before.map((row, index) => row.step_order ?? index + 1)) : 0;
  const insertedOrder = baseOrder + 1;
  const inserted: EApprovalWorkflowStepInput = {
    ...step,
    step_order: insertedOrder,
  };
  const after = compactWorkflowStepOrdersPreservingTies(afterSource).map((row, index) => ({
    ...row,
    step_order: (row.step_order ?? index + 1) + insertedOrder,
  }));

  return [...before, inserted, ...after];
}
