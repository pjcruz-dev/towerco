import type { EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

export type ParallelCompletionMode = "all" | "any" | "n_of_m";

export type ParallelApprovalGroup = {
  stepOrder: number;
  memberIndexes: number[];
  startIndex: number;
  endIndex: number;
  mode: ParallelCompletionMode;
  quorum: number;
};

/**
 * Remap step_order to compact 1..N while keeping siblings that share the same
 * original order on the same new order (parallel band).
 */
export function compactWorkflowStepOrdersPreservingTies(
  steps: EApprovalWorkflowStepInput[],
): EApprovalWorkflowStepInput[] {
  if (steps.length === 0) {
    return [];
  }

  const uniqueOrders: number[] = [];
  for (const [index, step] of steps.entries()) {
    const order = step.step_order ?? index + 1;
    if (!uniqueOrders.includes(order)) {
      uniqueOrders.push(order);
    }
  }

  uniqueOrders.sort((a, b) => a - b);
  const orderMap = new Map(uniqueOrders.map((order, index) => [order, index + 1]));

  return steps.map((step, index) => {
    const oldOrder = step.step_order ?? index + 1;
    return {
      ...step,
      step_order: orderMap.get(oldOrder) ?? index + 1,
    };
  });
}

export function normalizeParallelMode(mode: string | null | undefined): ParallelCompletionMode {
  if (mode === "any" || mode === "n_of_m") {
    return mode;
  }
  return "all";
}

export function resolveParallelQuorum(
  mode: ParallelCompletionMode,
  memberCount: number,
  quorum?: number | null,
): number {
  if (mode === "any") {
    return 1;
  }
  if (mode !== "n_of_m") {
    return Math.max(1, memberCount);
  }
  const raw = typeof quorum === "number" && Number.isFinite(quorum) ? Math.floor(quorum) : 1;
  return Math.max(1, Math.min(memberCount, raw));
}

function parallelMetaFromStep(
  step: EApprovalWorkflowStepInput | undefined,
  memberCount: number,
): { mode: ParallelCompletionMode; quorum: number } {
  const mode = normalizeParallelMode(step?.parallel_mode);
  return {
    mode,
    quorum: resolveParallelQuorum(mode, memberCount, step?.parallel_quorum),
  };
}

/** Groups of 2+ steps that share the same step_order. */
export function detectParallelApprovalGroups(
  steps: EApprovalWorkflowStepInput[],
): ParallelApprovalGroup[] {
  const byOrder = new Map<number, number[]>();

  steps.forEach((step, index) => {
    const order = step.step_order ?? index + 1;
    const members = byOrder.get(order) ?? [];
    members.push(index);
    byOrder.set(order, members);
  });

  return [...byOrder.entries()]
    .filter(([, memberIndexes]) => memberIndexes.length >= 2)
    .sort((a, b) => a[0] - b[0])
    .map(([stepOrder, memberIndexes]) => {
      const sorted = [...memberIndexes].sort((a, b) => a - b);
      const meta = parallelMetaFromStep(steps[sorted[0]], sorted.length);
      return {
        stepOrder,
        memberIndexes: sorted,
        startIndex: Math.min(...sorted),
        endIndex: Math.max(...sorted),
        mode: meta.mode,
        quorum: meta.quorum,
      };
    });
}

export type ParallelApprovalStepInput = {
  approverIds: string[];
  mode?: ParallelCompletionMode;
  quorum?: number;
};

function withParallelMeta(
  step: EApprovalWorkflowStepInput,
  mode: ParallelCompletionMode,
  quorum: number,
  memberCount: number,
): EApprovalWorkflowStepInput {
  if (mode === "all") {
    const next = { ...step };
    delete next.parallel_mode;
    delete next.parallel_quorum;
    return next;
  }

  return {
    ...step,
    parallel_mode: mode,
    ...(mode === "n_of_m"
      ? { parallel_quorum: resolveParallelQuorum(mode, memberCount, quorum) }
      : { parallel_quorum: undefined }),
  };
}

/** Insert N fixed-user steps that share one step_order. */
export function insertParallelApprovalSteps(
  steps: EApprovalWorkflowStepInput[],
  input: ParallelApprovalStepInput,
  atIndex?: number,
): EApprovalWorkflowStepInput[] {
  const approverIds = input.approverIds.map((id) => id.trim()).filter(Boolean);
  if (approverIds.length < 2) {
    return steps;
  }

  const mode = normalizeParallelMode(input.mode);
  const quorum = resolveParallelQuorum(mode, approverIds.length, input.quorum);
  const insertAt = Math.max(0, Math.min(atIndex ?? steps.length, steps.length));
  const before = compactWorkflowStepOrdersPreservingTies(steps.slice(0, insertAt));
  const afterSource = steps.slice(insertAt);
  const baseOrder =
    before.length > 0 ? Math.max(...before.map((step, index) => step.step_order ?? index + 1)) : 0;
  const parallelOrder = baseOrder + 1;

  const parallelSteps: EApprovalWorkflowStepInput[] = approverIds.map((approverId) =>
    withParallelMeta(
      {
        type: "user",
        approverId,
        step_order: parallelOrder,
      },
      mode,
      quorum,
      approverIds.length,
    ),
  );

  const after = compactWorkflowStepOrdersPreservingTies(afterSource).map((step, index) => ({
    ...step,
    step_order: (step.step_order ?? index + 1) + parallelOrder,
  }));

  return [...before, ...parallelSteps, ...after];
}

export function removeParallelApprovalGroup(
  steps: EApprovalWorkflowStepInput[],
  group: ParallelApprovalGroup,
): EApprovalWorkflowStepInput[] {
  const remove = new Set(group.memberIndexes);
  return compactWorkflowStepOrdersPreservingTies(
    steps.filter((_, index) => !remove.has(index)),
  );
}

export function addMemberToParallelGroup(
  steps: EApprovalWorkflowStepInput[],
  group: ParallelApprovalGroup,
  approverId: string,
): EApprovalWorkflowStepInput[] {
  const trimmed = approverId.trim();
  if (!trimmed) {
    return steps;
  }

  const insertAt = group.endIndex + 1;
  const nextMemberCount = group.memberIndexes.length + 1;
  const next = [
    ...steps.slice(0, insertAt),
    withParallelMeta(
      { type: "user", approverId: trimmed, step_order: group.stepOrder },
      group.mode,
      group.quorum,
      nextMemberCount,
    ),
    ...steps.slice(insertAt),
  ];
  const compacted = compactWorkflowStepOrdersPreservingTies(next);
  const refreshed = detectParallelApprovalGroups(compacted).find((item) =>
    item.memberIndexes.some((index) => compacted[index]?.approverId === trimmed),
  );

  if (!refreshed) {
    return compacted;
  }

  return setParallelGroupMode(compacted, refreshed, group.mode, group.quorum);
}

/** Apply completion mode to every member of a parallel band. */
export function setParallelGroupMode(
  steps: EApprovalWorkflowStepInput[],
  group: ParallelApprovalGroup,
  mode: ParallelCompletionMode,
  quorum?: number,
): EApprovalWorkflowStepInput[] {
  const memberCount = group.memberIndexes.length;
  const resolvedQuorum = resolveParallelQuorum(mode, memberCount, quorum);

  return steps.map((step, index) => {
    if (!group.memberIndexes.includes(index)) {
      return step;
    }
    return withParallelMeta(step, mode, resolvedQuorum, memberCount);
  });
}

export function parallelModeLabel(mode: ParallelCompletionMode, quorum: number, memberCount: number): string {
  if (mode === "any") {
    return "Any one can approve";
  }
  if (mode === "n_of_m") {
    return `At least ${quorum} of ${memberCount} must approve`;
  }
  return "All must approve";
}
