import { filterCmeReportsForPhase, phaseWorkPanelKind } from "@/lib/rollout/phase-work-panels";
import { countSecuredPermits, phaseHasPermitsPanel } from "@/lib/rollout/phase-permits";
import type { RolloutDetail, RolloutTimelinePhase } from "@/modules/rollout/types";

export type PhaseReadinessTone = "success" | "warning" | "danger" | "neutral" | "info";

export type PhaseReadiness = {
  tone: PhaseReadinessTone;
  label: string;
};

/** PMO must set rollout endorsement date before SAQ site-hunting work applies. */
export function isEndorsementEstablished(detail: RolloutDetail): boolean {
  if (detail.endorsement_date) {
    return true;
  }

  const endorsement = detail.timeline_phases?.find((p) => p.phase_key === "endorsement");
  if (!endorsement) {
    return false;
  }

  return (
    endorsement.gate_status === "passed" ||
    endorsement.phase_progress === "completed" ||
    Boolean(endorsement.actual_end_date)
  );
}

const MIN_ACTIVE_CANDIDATES = 3;

export function activeSaqCandidateCount(detail: RolloutDetail): number {
  return detail.candidates?.filter((c) => c.status !== "rejected").length ?? 0;
}

export function hasSelectedSaqCandidate(detail: RolloutDetail): boolean {
  return Boolean(detail.candidates?.some((c) => c.status === "selected"));
}

/** P2 — ≥3 active candidates ready for selection. */
export function isSaqReadyToSelect(detail: RolloutDetail): boolean {
  return isEndorsementEstablished(detail) && activeSaqCandidateCount(detail) >= MIN_ACTIVE_CANDIDATES;
}

/** P2 — Site Hunting gate may be requested / passed. */
export function isSiteHuntingGateReady(detail: RolloutDetail): boolean {
  return isSaqReadyToSelect(detail) && hasSelectedSaqCandidate(detail);
}

export function isSiteHuntingGatePassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "site_hunting");
  if (!phase) {
    return isSiteHuntingGateReady(detail);
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

export function hasPreAssessmentPhase(detail: RolloutDetail): boolean {
  return Boolean(detail.timeline_phases?.some((p) => p.phase_key === "pre_assessment"));
}

export function isPreAssessmentPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "pre_assessment");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P3 — MNO Pre-assessment may be requested / passed. */
export function isPreAssessmentReady(detail: RolloutDetail): boolean {
  return (
    isEndorsementEstablished(detail) &&
    hasSelectedSaqCandidate(detail) &&
    isSiteHuntingGatePassed(detail)
  );
}

export function hasMocColPhase(detail: RolloutDetail): boolean {
  return Boolean(detail.timeline_phases?.some((p) => p.phase_key === "moc_col"));
}

export function isMocColPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "moc_col");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P4 — MOC/COL may be requested / passed after P3. */
export function isMocColReady(detail: RolloutDetail): boolean {
  return isPreAssessmentReady(detail) && isPreAssessmentPassed(detail);
}

export function hasTssrCreationPhase(detail: RolloutDetail): boolean {
  return Boolean(detail.timeline_phases?.some((p) => p.phase_key === "tssr_creation"));
}

export function isTssrCreationPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "tssr_creation");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

export function isDayOneSet(detail: RolloutDetail): boolean {
  return Boolean(detail.tssr_approved_date);
}

/** P5 — TSSR create/review may proceed after P3+P4. */
export function isTssrCreationReady(detail: RolloutDetail): boolean {
  if (hasPreAssessmentPhase(detail) && !isPreAssessmentPassed(detail)) {
    return false;
  }
  if (hasMocColPhase(detail) && !isMocColPassed(detail)) {
    return false;
  }
  return isMocColReady(detail) || (!hasPreAssessmentPhase(detail) && !hasMocColPhase(detail) && isEndorsementEstablished(detail));
}

/** P5 — Day-1 may be recorded after TSSR create/review passed. */
export function isDayOneReady(detail: RolloutDetail): boolean {
  return isTssrCreationReady(detail) && isTssrCreationPassed(detail);
}

export function isPreConstructionPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "pre_construction");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P6 — Pre-Construction may proceed after Day-1. */
export function isPreConstructionReady(detail: RolloutDetail): boolean {
  return isDayOneSet(detail);
}

export function isPermittingPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "permitting");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P6 — Permitting after Pre-Construction (when present). */
export function isPermittingReady(detail: RolloutDetail): boolean {
  return isPreConstructionReady(detail) && isPreConstructionPassed(detail);
}

export function isSkomPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "skom");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P6 — SKOM after Permitting (when present). */
export function isSkomReady(detail: RolloutDetail): boolean {
  return isPermittingReady(detail) && isPermittingPassed(detail);
}

/** P6 complete — Construction (P7) may start. */
export function isBuildReadinessComplete(detail: RolloutDetail): boolean {
  return isSkomReady(detail) && isSkomPassed(detail);
}

export function isRfiRecorded(detail: RolloutDetail): boolean {
  return Boolean(detail.actual_rfi_date);
}

export function isConstructionPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "construction");
  if (!phase) {
    return isRfiRecorded(detail);
  }
  return (
    phase.gate_status === "passed" ||
    phase.phase_progress === "completed" ||
    Boolean(phase.actual_end_date) ||
    isRfiRecorded(detail)
  );
}

/** P7 — Construction may proceed after P6. */
export function isConstructionReady(detail: RolloutDetail): boolean {
  return isBuildReadinessComplete(detail);
}

/** P7 — RFI (★ site ready) after P6; Day-1 already implied by P6. */
export function isRfiReady(detail: RolloutDetail): boolean {
  return isDayOneSet(detail) && isBuildReadinessComplete(detail);
}

export function isSiteLicensePassed(detail: RolloutDetail): boolean {
  if (detail.site_license_executed_date) {
    return true;
  }
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "site_license");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P8 — Site License after RFI. */
export function isSiteLicenseReady(detail: RolloutDetail): boolean {
  return isRfiRecorded(detail);
}

export function isHandoverPassed(detail: RolloutDetail): boolean {
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "handover_operations");
  if (!phase) {
    return true;
  }
  return phase.gate_status === "passed" || phase.phase_progress === "completed" || Boolean(phase.actual_end_date);
}

/** P8 — Handover after Site License. */
export function isHandoverReady(detail: RolloutDetail): boolean {
  return isSiteLicenseReady(detail) && isSiteLicensePassed(detail);
}

/** P8 complete. */
export function isCloseOutComplete(detail: RolloutDetail): boolean {
  return isRfiRecorded(detail) && isSiteLicensePassed(detail) && isHandoverPassed(detail);
}

export function selectedSaqCandidate(detail: RolloutDetail) {
  return detail.candidates?.find((c) => c.status === "selected") ?? null;
}

export function phaseGateReadiness(
  phase: RolloutTimelinePhase,
  detail: RolloutDetail,
): PhaseReadiness | null {
  if (phase.gate_status === "passed" || phase.phase_progress === "completed") {
    return { tone: "success", label: "Gate passed" };
  }

  if (phase.active_gate_approval?.status === "in_review") {
    return { tone: "info", label: "Approval in review" };
  }

  if (phase.phase_progress === "overdue") {
    return { tone: "danger", label: "Overdue" };
  }

  if (phase.phase_key === "endorsement" && !isEndorsementEstablished(detail)) {
    return { tone: "warning", label: "Set endorsement date" };
  }

  if (phase.phase_key === "site_hunting" && !isEndorsementEstablished(detail)) {
    return { tone: "neutral", label: "Awaiting endorsement" };
  }

  if (phase.phase_key === "site_hunting") {
    const active = activeSaqCandidateCount(detail);
    if (active < MIN_ACTIVE_CANDIDATES) {
      return {
        tone: "warning",
        label: `Need ${MIN_ACTIVE_CANDIDATES - active} more candidate${MIN_ACTIVE_CANDIDATES - active === 1 ? "" : "s"}`,
      };
    }
    if (!hasSelectedSaqCandidate(detail)) {
      return { tone: "warning", label: "Select a candidate" };
    }
    return { tone: "success", label: "Ready — request gate" };
  }

  if (phase.phase_key === "pre_assessment") {
    if (!isSiteHuntingGatePassed(detail)) {
      return { tone: "neutral", label: "Awaiting Site Hunting" };
    }
    if (!hasSelectedSaqCandidate(detail)) {
      return { tone: "warning", label: "Select a candidate first" };
    }
    return { tone: "success", label: "Ready — MNO approval" };
  }

  if (phase.phase_key === "moc_col") {
    if (!isPreAssessmentPassed(detail)) {
      return { tone: "neutral", label: "Awaiting Pre-assessment" };
    }
    return { tone: "success", label: "Ready — eLAS IRR" };
  }

  if (phase.phase_key === "tssr_creation") {
    if (hasPreAssessmentPhase(detail) && !isPreAssessmentPassed(detail)) {
      return { tone: "neutral", label: "Awaiting Pre-assessment" };
    }
    if (hasMocColPhase(detail) && !isMocColPassed(detail)) {
      return { tone: "neutral", label: "Awaiting MOC/COL" };
    }
    return { tone: "success", label: "Ready — Engineering gate" };
  }

  if (phase.phase_key === "tssr_mno_approval") {
    if (!isTssrCreationPassed(detail)) {
      return { tone: "neutral", label: "Awaiting TSSR create/review" };
    }
    if (isDayOneSet(detail)) {
      return { tone: "success", label: "Day-1 recorded" };
    }
    return { tone: "success", label: "Ready — record Day-1" };
  }

  if (phase.phase_key === "pre_construction") {
    if (!isDayOneSet(detail)) {
      return { tone: "neutral", label: "Awaiting Day-1" };
    }
    return { tone: "success", label: "Ready — Pre-Construction" };
  }

  if (phase.phase_key === "permitting") {
    if (!isDayOneSet(detail)) {
      return { tone: "neutral", label: "Awaiting Day-1" };
    }
    if (!isPreConstructionPassed(detail)) {
      return { tone: "neutral", label: "Awaiting Pre-Construction" };
    }
    return { tone: "success", label: "Ready — Permitting" };
  }

  if (phase.phase_key === "skom") {
    if (!isPermittingPassed(detail)) {
      return { tone: "neutral", label: "Awaiting Permitting" };
    }
    return { tone: "success", label: "Ready — SKOM" };
  }

  if (phase.phase_key === "construction") {
    if (!isBuildReadinessComplete(detail)) {
      return { tone: "neutral", label: "Awaiting Pre-con → SKOM" };
    }
    if (isRfiRecorded(detail)) {
      return { tone: "success", label: "★ Site ready (RFI)" };
    }
    return { tone: "success", label: "Ready — record RFI" };
  }

  if (phase.phase_key === "site_license") {
    if (!isRfiRecorded(detail)) {
      return { tone: "neutral", label: "Awaiting RFI (site ready)" };
    }
    return { tone: "success", label: "Ready — Site License" };
  }

  if (phase.phase_key === "handover_operations") {
    if (!isRfiRecorded(detail)) {
      return { tone: "neutral", label: "Awaiting RFI (site ready)" };
    }
    if (!isSiteLicensePassed(detail)) {
      return { tone: "neutral", label: "Awaiting Site License" };
    }
    return { tone: "success", label: "Ready — Handover" };
  }

  if (phaseWorkPanelKind(phase.phase_key) === "cme" && phase.phase_progress === "active") {
    const reports = filterCmeReportsForPhase(
      detail.cme_reports ?? [],
      phase.phase_key,
      detail.timeline_phases ?? [],
    );
    const today = new Date().toISOString().slice(0, 10);
    const hasToday = reports.some((r) => r.report_date === today);
    if (!hasToday) {
      return { tone: "warning", label: "Log today’s report" };
    }
    return { tone: "neutral", label: `${reports.length} report${reports.length === 1 ? "" : "s"}` };
  }

  if (phaseHasPermitsPanel(phase.phase_key)) {
    const { secured, total } = countSecuredPermits(detail.permits ?? [], phase.phase_key);
    if (total === 0) {
      return null;
    }
    if (secured === total) {
      return { tone: "success", label: "All permits secured" };
    }
    return { tone: "warning", label: `${secured}/${total} secured` };
  }

  return null;
}

export type TimelineContextItem = {
  id: string;
  label: string;
  value: string;
  tone?: PhaseReadinessTone;
};

export function buildTimelineContextStrip(detail: RolloutDetail): TimelineContextItem[] {
  const phases = detail.timeline_phases ?? [];
  const items: TimelineContextItem[] = [];
  const endorsementReady = isEndorsementEstablished(detail);

  if (!endorsementReady) {
    items.push({
      id: "endorsement",
      label: "Endorsement",
      value: "Not set — Site Tracker first",
      tone: "warning",
    });
  }

  const active = phases.find((p) => p.phase_progress === "active");
  if (active) {
    items.push({
      id: "active-phase",
      label: "Active phase",
      value: active.label,
      tone: "info",
    });
  }

  const overdue = phases.filter((p) => p.phase_progress === "overdue");
  if (overdue.length > 0) {
    items.push({
      id: "overdue",
      label: "Overdue",
      value: `${overdue.length} phase${overdue.length === 1 ? "" : "s"}`,
      tone: "danger",
    });
  }

  const awaitingApproval = phases.filter((p) => p.active_gate_approval?.status === "in_review");
  if (awaitingApproval.length > 0) {
    items.push({
      id: "approvals",
      label: "Gate approval",
      value: `${awaitingApproval.length} in review`,
      tone: "info",
    });
  }

  const siteHunting = phases.find((p) => p.phase_key === "site_hunting");
  if (endorsementReady && siteHunting && siteHunting.phase_progress !== "completed" && siteHunting.gate_status !== "passed") {
    const count = activeSaqCandidateCount(detail);
    const selected = hasSelectedSaqCandidate(detail);
    items.push({
      id: "saq",
      label: "SAQ",
      value: selected
        ? `${count}/3 · selected — request gate`
        : `${count}/3${count >= MIN_ACTIVE_CANDIDATES ? " · select next" : ""}`,
      tone: isSiteHuntingGateReady(detail) ? "success" : "warning",
    });
  }

  const preAssessment = phases.find((p) => p.phase_key === "pre_assessment");
  if (
    preAssessment &&
    preAssessment.gate_status !== "passed" &&
    preAssessment.phase_progress !== "completed" &&
    isSiteHuntingGatePassed(detail)
  ) {
    const selected = selectedSaqCandidate(detail);
    items.push({
      id: "pre_assessment",
      label: "Pre-assessment",
      value: selected
        ? `${selected.label ?? `Candidate #${selected.candidate_number}`} — MNO review`
        : "Select candidate first",
      tone: isPreAssessmentReady(detail) ? "success" : "warning",
    });
  }

  const mocCol = phases.find((p) => p.phase_key === "moc_col");
  if (
    mocCol &&
    mocCol.gate_status !== "passed" &&
    mocCol.phase_progress !== "completed" &&
    isPreAssessmentPassed(detail)
  ) {
    items.push({
      id: "moc_col",
      label: "MOC/COL",
      value: isMocColReady(detail) ? "eLAS IRR — request gate" : "Awaiting Pre-assessment",
      tone: isMocColReady(detail) ? "success" : "warning",
    });
  }

  const tssrCreation = phases.find((p) => p.phase_key === "tssr_creation");
  if (
    tssrCreation &&
    tssrCreation.gate_status !== "passed" &&
    isTssrCreationReady(detail)
  ) {
    items.push({
      id: "tssr",
      label: "TSSR",
      value: "Engineering review",
      tone: "success",
    });
  } else if (isTssrCreationPassed(detail) && !isDayOneSet(detail)) {
    items.push({
      id: "day_one",
      label: "Day-1",
      value: "Record TSSR approved date",
      tone: "warning",
    });
  }

  if (isDayOneSet(detail) && !isBuildReadinessComplete(detail)) {
    const value = !isPreConstructionPassed(detail)
      ? "Pre-Construction"
      : !isPermittingPassed(detail)
        ? "Permitting"
        : "SKOM";
    items.push({
      id: "build_readiness",
      label: "Build readiness",
      value,
      tone: "info",
    });
  } else if (isBuildReadinessComplete(detail) && !isRfiRecorded(detail)) {
    items.push({
      id: "rfi",
      label: "Site ready",
      value: "Record RFI",
      tone: "warning",
    });
  } else if (isRfiRecorded(detail) && !isCloseOutComplete(detail)) {
    items.push({
      id: "close_out",
      label: "Close-out",
      value: !isSiteLicensePassed(detail) ? "Site License" : "Handover to Ops",
      tone: "info",
    });
  } else if (isCloseOutComplete(detail)) {
    items.push({
      id: "close_out_done",
      label: "Close-out",
      value: "Complete",
      tone: "success",
    });
  }

  if (detail.sla_working_days_remaining != null) {
    items.push({
      id: "sla",
      label: "SLA remaining",
      value: `${detail.sla_working_days_remaining} wd`,
      tone: detail.sla_working_days_remaining <= 10 ? "warning" : "neutral",
    });
  }

  return items;
}
