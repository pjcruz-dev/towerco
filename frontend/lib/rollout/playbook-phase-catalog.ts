import type { RolloutPlaybookPhaseTemplate } from "@/modules/rollout/types";

/** Unique playbook phases across BTS / RTB / Colocation templates for bulk editors. */
export function mergePlaybookPhaseCatalog(
  templates: Record<string, RolloutPlaybookPhaseTemplate[]> | undefined,
): RolloutPlaybookPhaseTemplate[] {
  const byKey = new Map<string, RolloutPlaybookPhaseTemplate>();

  for (const phases of Object.values(templates ?? {})) {
    for (const phase of phases) {
      if (!byKey.has(phase.phase_key)) {
        byKey.set(phase.phase_key, phase);
      }
    }
  }

  return Array.from(byKey.values()).sort((a, b) => {
    const startDiff = (a.working_day_start ?? 0) - (b.working_day_start ?? 0);
    if (startDiff !== 0) {
      return startDiff;
    }

    return a.label.localeCompare(b.label);
  });
}
