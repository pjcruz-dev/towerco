import type { RolloutPermitRow } from "@/modules/rollout/types";

import { phaseHasWorkPanel } from "@/lib/rollout/phase-work-panels";

/** Timeline phases that embed permit checkpoint forms. */
export const PHASE_PERMIT_PANEL_KEYS = new Set(["moc_col", "permitting"]);

export function phaseHasPermitsPanel(phaseKey: string): boolean {
  return PHASE_PERMIT_PANEL_KEYS.has(phaseKey);
}

export function phaseIsExpandable(phaseKey: string): boolean {
  return phaseHasWorkPanel(phaseKey) || phaseHasPermitsPanel(phaseKey);
}

export function permitsForTimelinePhase(permits: RolloutPermitRow[], phaseKey: string): RolloutPermitRow[] {
  return permits.filter((row) => row.timeline_phase_key === phaseKey);
}

export function phasePermitsSummary(permits: RolloutPermitRow[], phaseKey: string): string | null {
  const rows = permitsForTimelinePhase(permits, phaseKey);
  if (rows.length === 0) {
    return null;
  }

  const secured = rows.filter((row) => row.secured_date).length;
  return `${secured}/${rows.length} secured`;
}

export function countSecuredPermits(permits: RolloutPermitRow[], phaseKey: string): { secured: number; total: number } {
  const rows = permitsForTimelinePhase(permits, phaseKey);

  return {
    secured: rows.filter((row) => row.secured_date).length,
    total: rows.length,
  };
}
