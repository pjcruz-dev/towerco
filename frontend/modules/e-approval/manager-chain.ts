import type { EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

function flagOn(value: unknown): boolean {
  return value === true || value === 1 || value === "1" || value === "true";
}

export function managerChainEnabled(step: EApprovalWorkflowStepInput): boolean {
  if (step.type !== "manager") {
    return false;
  }
  if (typeof step.manager_chain === "boolean") {
    return step.manager_chain;
  }

  return flagOn(step.condition?.manager_chain);
}

export function managerChainStopTitles(step: EApprovalWorkflowStepInput): string {
  if (typeof step.manager_chain_stop_titles === "string") {
    return step.manager_chain_stop_titles;
  }
  const stored = step.condition?.manager_chain_stop_titles;

  return typeof stored === "string" ? stored : "Director";
}

export function managerChainMaxLevels(step: EApprovalWorkflowStepInput): number {
  const raw = step.manager_chain_max_levels ?? step.condition?.manager_chain_max_levels ?? 6;
  const levels = Number(raw);
  if (!Number.isFinite(levels)) {
    return 6;
  }

  return Math.max(1, Math.min(8, Math.round(levels)));
}

/** Subtitle on the form workflow path while the requestor is not known yet. */
export function managerChainCanvasSubtitle(step: EApprovalWorkflowStepInput): string | null {
  if (!managerChainEnabled(step)) {
    return null;
  }
  const titles = managerChainStopTitles(step).trim();
  if (titles !== "") {
    return `Until ${titles}`;
  }

  return `Up to ${managerChainMaxLevels(step)} levels`;
}
