import { isEndorsementEstablished } from "@/lib/rollout/phase-gate-readiness";
import { phaseHasPermitsPanel } from "@/lib/rollout/phase-permits";
import type { RolloutCmeReport, RolloutDetail, RolloutTimelinePhase } from "@/modules/rollout/types";

export type PhaseWorkPanelKind = "saq" | "cme" | "pre_assessment" | "tssr" | "close_out";

/** Playbook phases that embed field-work forms under the timeline row. */
export const PHASE_WORK_PANEL_KIND: Record<string, PhaseWorkPanelKind> = {
  site_hunting: "saq",
  pre_assessment: "pre_assessment",
  tssr_creation: "tssr",
  tssr_mno_approval: "tssr",
  pre_construction: "cme",
  construction: "cme",
  skom: "cme",
  site_license: "close_out",
  handover_operations: "close_out",
};

export function phaseWorkPanelKind(phaseKey: string): PhaseWorkPanelKind | null {
  return PHASE_WORK_PANEL_KIND[phaseKey] ?? null;
}

export function phaseHasWorkPanel(phaseKey: string): boolean {
  return phaseKey in PHASE_WORK_PANEL_KIND;
}

export function resolveDefaultExpandedPhaseKey(
  phases: RolloutTimelinePhase[],
  urlPhaseKey: string | null,
  detail?: RolloutDetail | null,
): string | null {
  if (urlPhaseKey && phases.some((p) => p.phase_key === urlPhaseKey && (phaseHasWorkPanel(urlPhaseKey) || phaseHasPermitsPanel(urlPhaseKey)))) {
    if (!detail || isEndorsementEstablished(detail) || urlPhaseKey !== "site_hunting") {
      return urlPhaseKey;
    }
  }

  const withPanel = phases.filter((p) => phaseHasWorkPanel(p.phase_key) || phaseHasPermitsPanel(p.phase_key));

  if (detail && !isEndorsementEstablished(detail)) {
    return null;
  }

  const active = withPanel.find((p) => p.phase_progress === "active" || p.phase_progress === "overdue");
  if (active) {
    return active.phase_key;
  }

  const incomplete = withPanel.find((p) => p.phase_progress !== "completed");
  if (incomplete) {
    return incomplete.phase_key;
  }

  return withPanel.at(-1)?.phase_key ?? null;
}

export function phaseWorkSummary(
  phaseKey: string,
  detail: RolloutDetail | undefined,
): string | null {
  if (!detail) {
    return null;
  }

  const kind = phaseWorkPanelKind(phaseKey);
  if (kind === "saq") {
    if (!isEndorsementEstablished(detail)) {
      return "PMO: set endorsement date first";
    }
    const count = detail.candidates?.length ?? 0;
    const logs = detail.hunting_logs?.length ?? 0;
    const parts: string[] = [];
    if (count > 0) {
      parts.push(`${count} candidate${count === 1 ? "" : "s"}`);
    }
    if (logs > 0) {
      parts.push(`${logs} log${logs === 1 ? "" : "s"}`);
    }
    if (count < 3) {
      parts.push(`need ${Math.max(0, 3 - count)} more`);
    } else if (!detail.candidates?.some((c) => c.status === "selected")) {
      parts.push("select one");
    } else {
      parts.push("request Site Hunting gate");
    }
    return parts.length > 0 ? parts.join(" · ") : "No candidates yet";
  }

  if (kind === "pre_assessment") {
    const selected = detail.candidates?.find((c) => c.status === "selected");
    if (!selected) {
      return "Select a candidate in Site Hunting first";
    }
    return `${selected.label ?? `Candidate #${selected.candidate_number}`} · MNO pre-assessment`;
  }

  if (kind === "tssr") {
    if (detail.tssr_approved_date) {
      return `Day-1 ${detail.tssr_approved_date}`;
    }
    return "Engineering review → record Day-1";
  }

  if (kind === "close_out") {
    if (detail.site_license_executed_date && phaseKey === "site_license") {
      return `License ${detail.site_license_executed_date}`;
    }
    if (phaseKey === "handover_operations") {
      return "Ops acceptance gate";
    }
    return "Post–RFI close-out";
  }

  if (kind === "cme") {
    if (phaseKey === "pre_construction" || phaseKey === "skom") {
      if (!detail.tssr_approved_date) {
        return "Record Day-1 first";
      }
    }
    if (phaseKey === "construction") {
      if (!detail.actual_rfi_date) {
        return detail.tssr_approved_date ? "Build → record RFI ★ site ready" : "Record Day-1 first";
      }
      return `★ Site ready ${detail.actual_rfi_date}`;
    }
    const reports = filterCmeReportsForPhase(detail.cme_reports ?? [], phaseKey, detail.timeline_phases ?? []);
    const count = reports.length;
    return count > 0 ? `${count} report${count === 1 ? "" : "s"}` : "No daily reports yet";
  }

  return null;
}

export function cmePhaseWorkHint(phaseKey: string): string {
  switch (phaseKey) {
    case "pre_construction":
      return "Pre-construction daily reports. Entries are scoped to this phase target window when dates are set.";
    case "construction":
      return "Construction and energization daily reports for this rollout.";
    case "skom":
      return "SKOM / mobilization daily reports.";
    default:
      return "Daily construction reports for this phase window.";
  }
}

export function filterCmeReportsForPhase(
  reports: RolloutCmeReport[],
  phaseKey: string,
  phases: RolloutTimelinePhase[],
): RolloutCmeReport[] {
  const phase = phases.find((p) => p.phase_key === phaseKey);
  if (!phase) {
    return reports;
  }

  const byPhaseId = reports.filter((r) => r.timeline_phase_id === phase.id);
  if (byPhaseId.length > 0) {
    return byPhaseId;
  }

  if (!phase.target_start_date || !phase.target_end_date) {
    return reports.filter((r) => !r.timeline_phase_id);
  }

  return reports.filter((report) => {
    if (report.timeline_phase_id) {
      return false;
    }
    if (!report.report_date) {
      return false;
    }
    return report.report_date >= phase.target_start_date! && report.report_date <= phase.target_end_date!;
  });
}

export function resolveTimelinePhaseId(phaseKey: string, phases: RolloutTimelinePhase[]): string | undefined {
  return phases.find((p) => p.phase_key === phaseKey)?.id;
}
