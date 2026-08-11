import type {
  EApprovalWorkflowPreviewResponse,
  EApprovalWorkflowPreviewSkippedStep,
  EApprovalWorkflowPreviewStep,
} from "@/lib/api/modules/e-approval-api";
import { E_APPROVAL_STEP_TYPES } from "@/modules/e-approval/field-types";

export type WorkflowDiagramNodeKind = "run" | "skipped";

export type WorkflowDiagramNode = {
  id: string;
  stepOrder: number;
  title: string;
  subtitle?: string;
  detail?: string | null;
  kind: WorkflowDiagramNodeKind;
  status?: string | null;
  warning?: string | null;
  /** Approval signature for hover on the approver name (submission path). */
  signature?: string | null;
};

export type WorkflowDiagramBand = {
  id: string;
  stepOrder: number;
  kind: WorkflowDiagramNodeKind;
  bandLabel: string | null;
  nodes: WorkflowDiagramNode[];
};

export function workflowStepTypeLabel(type: string): string {
  return E_APPROVAL_STEP_TYPES.find((item) => item.value === type)?.label ?? type;
}

export function describeParallelBandLabel(
  members: Pick<EApprovalWorkflowPreviewStep, "parallel_mode" | "parallel_quorum">[],
): string | null {
  if (members.length < 2) {
    return null;
  }

  const mode = members.find((row) => row.parallel_mode)?.parallel_mode ?? "all";
  const quorum = members.find((row) => row.parallel_quorum != null)?.parallel_quorum ?? 1;

  if (mode === "any") {
    return "Parallel · Any one";
  }
  if (mode === "n_of_m") {
    return `Parallel · ${quorum} of ${members.length}`;
  }

  return "Parallel · All must approve";
}

function groupByStepOrder(
  steps: EApprovalWorkflowPreviewStep[],
): Array<[number, EApprovalWorkflowPreviewStep[]]> {
  const byOrder = new Map<number, EApprovalWorkflowPreviewStep[]>();
  for (const step of steps) {
    const list = byOrder.get(step.step_order) ?? [];
    list.push(step);
    byOrder.set(step.step_order, list);
  }

  return [...byOrder.entries()].sort((a, b) => a[0] - b[0]);
}

function runNodeFromStep(step: EApprovalWorkflowPreviewStep, memberIndex: number): WorkflowDiagramNode {
  const runtimeStatus = step.runtime_status?.trim().toLowerCase() || null;
  const isRuntimeSkipped = runtimeStatus === "skipped";
  const name =
    step.runtime_approver?.name?.trim() ||
    step.resolved_user_name?.trim() ||
    step.label;
  const email = step.runtime_approver?.email ?? step.resolved_user_email;

  return {
    id: `run-${step.step_order}-${step.resolved_user_id ?? memberIndex}-${step.approval_id ?? "x"}`,
    stepOrder: step.step_order,
    title: name,
    subtitle: `${workflowStepTypeLabel(step.type)}${email ? ` · ${email}` : ""}`,
    detail: step.path_reason ?? null,
    // Keep order on the main path; render like condition-skipped cards.
    kind: isRuntimeSkipped ? "skipped" : "run",
    status: isRuntimeSkipped ? "skipped" : runtimeStatus,
    warning: isRuntimeSkipped
      ? null
      : (step.warning ?? (step.used_fallback ? "Used fallback approver" : null)),
    signature: step.signature ?? null,
  };
}

function skippedNodeFromStep(
  step: EApprovalWorkflowPreviewSkippedStep,
  index: number,
): WorkflowDiagramNode {
  return {
    id: `skip-${step.step_order}-${step.type}-${index}`,
    stepOrder: step.step_order,
    title: step.label,
    subtitle: workflowStepTypeLabel(step.type),
    detail: step.path_reason ?? null,
    kind: "skipped",
    status: "skipped",
  };
}

/** Build vertical diagram bands: runs first (by order), then skipped rail. */
export function buildWorkflowPathDiagram(
  preview: Pick<EApprovalWorkflowPreviewResponse, "resolved_steps" | "skipped_steps">,
): { runBands: WorkflowDiagramBand[]; skippedBands: WorkflowDiagramBand[] } {
  const runBands: WorkflowDiagramBand[] = groupByStepOrder(preview.resolved_steps).map(
    ([order, members]) => ({
      id: `band-run-${order}`,
      stepOrder: order,
      kind: "run" as const,
      bandLabel: describeParallelBandLabel(members),
      nodes: members.map((step, index) => runNodeFromStep(step, index)),
    }),
  );

  const skippedByOrder = new Map<number, EApprovalWorkflowPreviewSkippedStep[]>();
  for (const skipped of preview.skipped_steps ?? []) {
    const list = skippedByOrder.get(skipped.step_order) ?? [];
    list.push(skipped);
    skippedByOrder.set(skipped.step_order, list);
  }

  const skippedBands: WorkflowDiagramBand[] = [...skippedByOrder.entries()]
    .sort((a, b) => a[0] - b[0])
    .map(([order, members]) => ({
      id: `band-skip-${order}`,
      stepOrder: order,
      kind: "skipped" as const,
      bandLabel: members.length > 1 ? "Skipped branch" : null,
      nodes: members.map((step, index) => skippedNodeFromStep(step, index)),
    }));

  return { runBands, skippedBands };
}
