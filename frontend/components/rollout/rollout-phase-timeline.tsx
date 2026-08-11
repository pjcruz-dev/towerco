"use client";

import dynamic from "next/dynamic";
import { ChevronDown, ChevronRight } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { GateLabelText, MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { PhaseReadinessBadge } from "@/components/rollout/phase-readiness-badge";
import { RolloutPhaseMetadataBadges } from "@/components/rollout/rollout-phase-metadata-badges";
import { RolloutEndorsementActions } from "@/components/rollout/rollout-endorsement-actions";
import { RolloutPhaseWorkShell } from "@/components/rollout/rollout-phase-work-shell";
import { RolloutRfiActions } from "@/components/rollout/rollout-rfi-actions";
import { RolloutTimelineActions } from "@/components/rollout/rollout-timeline-actions";
import { RolloutTimelineContextStrip } from "@/components/rollout/rollout-timeline-context-strip";
import { RolloutTimelineGateSelect } from "@/components/rollout/rollout-timeline-gate-select";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { isEndorsementEstablished } from "@/lib/rollout/phase-gate-readiness";
import {
  phaseHasPermitsPanel,
  phaseIsExpandable,
  phasePermitsSummary,
} from "@/lib/rollout/phase-permits";
import {
  phaseHasWorkPanel,
  phaseWorkPanelKind,
  phaseWorkSummary,
  resolveDefaultExpandedPhaseKey,
} from "@/lib/rollout/phase-work-panels";
import { readPreferFocusTimeline, writePreferFocusTimeline } from "@/lib/rollout/timeline-view-preference";
import type { RolloutDetail, RolloutTimelinePhase } from "@/modules/rollout/types";

// Heavy per-phase work panels only render when a phase is expanded, so load them on demand
// to keep the rollout detail page's initial bundle small.
const phasePanelLoading = () => (
  <div className="h-40 w-full animate-pulse rounded-md bg-muted/40" aria-hidden />
);

const RolloutApprovalsActivityPanel = dynamic(
  () =>
    import("@/components/rollout/rollout-approvals-activity-panel").then(
      (m) => m.RolloutApprovalsActivityPanel,
    ),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutBuildReadinessPanel = dynamic(
  () =>
    import("@/components/rollout/rollout-build-readiness-panel").then(
      (m) => m.RolloutBuildReadinessPanel,
    ),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutCloseOutPanel = dynamic(
  () => import("@/components/rollout/rollout-close-out-panel").then((m) => m.RolloutCloseOutPanel),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutConstructionPanel = dynamic(
  () =>
    import("@/components/rollout/rollout-construction-panel").then((m) => m.RolloutConstructionPanel),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutCmeWorkPanel = dynamic(
  () => import("@/components/rollout/rollout-cme-tab").then((m) => m.RolloutCmeWorkPanel),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutPhasePermitsPanel = dynamic(
  () =>
    import("@/components/rollout/rollout-phase-permits-panel").then((m) => m.RolloutPhasePermitsPanel),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutSaqWorkPanel = dynamic(
  () => import("@/components/rollout/rollout-saq-tab").then((m) => m.RolloutSaqWorkPanel),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutPreAssessmentPanel = dynamic(
  () =>
    import("@/components/rollout/rollout-pre-assessment-panel").then(
      (m) => m.RolloutPreAssessmentPanel,
    ),
  { ssr: false, loading: phasePanelLoading },
);
const RolloutTssrPanel = dynamic(
  () => import("@/components/rollout/rollout-tssr-panel").then((m) => m.RolloutTssrPanel),
  { ssr: false, loading: phasePanelLoading },
);

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
  canManageRollout: boolean;
  canSaq: boolean;
  canCme: boolean;
  initialPhaseKey?: string | null;
};

type TimelineViewMode = "overview" | "focus";

export function RolloutPhaseTimeline({
  rolloutId,
  detail,
  canManageRollout,
  canSaq,
  canCme,
  initialPhaseKey = null,
}: Props) {
  const phases = detail.timeline_phases ?? [];
  const defaultExpanded = useMemo(
    () => resolveDefaultExpandedPhaseKey(phases, initialPhaseKey ?? null, detail),
    [phases, initialPhaseKey, detail],
  );
  const [expandedPhaseKey, setExpandedPhaseKey] = useState<string | null>(defaultExpanded);
  const [viewMode, setViewMode] = useState<TimelineViewMode>(() =>
    defaultExpanded && readPreferFocusTimeline() ? "focus" : "overview",
  );
  const didApplyUrlPhase = useRef(false);

  const expandablePhases = useMemo(
    () => phases.filter((p) => phaseIsExpandable(p.phase_key)),
    [phases],
  );

  const expandedPhase = useMemo(
    () => phases.find((p) => p.phase_key === expandedPhaseKey) ?? null,
    [phases, expandedPhaseKey],
  );

  const tablePhases = useMemo(() => {
    if (viewMode === "focus" && expandedPhaseKey) {
      return phases.filter((p) => p.phase_key === expandedPhaseKey);
    }
    return phases;
  }, [phases, viewMode, expandedPhaseKey]);

  const setFocusMode = useCallback((focus: boolean) => {
    setViewMode(focus ? "focus" : "overview");
    writePreferFocusTimeline(focus);
  }, []);

  useEffect(() => {
    setExpandedPhaseKey(defaultExpanded);
    if (defaultExpanded && readPreferFocusTimeline()) {
      setViewMode("focus");
    }
  }, [defaultExpanded]);

  useEffect(() => {
    if (!initialPhaseKey || didApplyUrlPhase.current) {
      return;
    }
    didApplyUrlPhase.current = true;
    if (phaseIsExpandable(initialPhaseKey)) {
      setExpandedPhaseKey(initialPhaseKey);
      setViewMode("focus");
      requestAnimationFrame(() => {
        document.getElementById(`rollout-phase-${initialPhaseKey}`)?.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }
  }, [initialPhaseKey]);

  function openPhaseWork(phaseKey: string) {
    if (!phaseIsExpandable(phaseKey)) {
      return;
    }
    setExpandedPhaseKey(phaseKey);
    setFocusMode(true);
  }

  function closePhaseWork() {
    setExpandedPhaseKey(null);
    setFocusMode(false);
  }

  function togglePhase(phaseKey: string) {
    if (!phaseIsExpandable(phaseKey)) {
      return;
    }
    if (expandedPhaseKey === phaseKey) {
      closePhaseWork();
      return;
    }
    openPhaseWork(phaseKey);
  }

  const expandedPhaseIndex = expandedPhaseKey
    ? expandablePhases.findIndex((p) => p.phase_key === expandedPhaseKey)
    : -1;
  const prevExpandablePhase = expandedPhaseIndex > 0 ? expandablePhases[expandedPhaseIndex - 1] : null;
  const nextExpandablePhase =
    expandedPhaseIndex >= 0 && expandedPhaseIndex < expandablePhases.length - 1
      ? expandablePhases[expandedPhaseIndex + 1]
      : null;

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
        return;
      }
      if (event.key === "Escape") {
        setExpandedPhaseKey(null);
        setFocusMode(false);
        return;
      }
      if (!expandedPhaseKey || viewMode !== "focus") {
        return;
      }
      const index = expandablePhases.findIndex((p) => p.phase_key === expandedPhaseKey);
      if (event.key === "ArrowLeft" && index > 0) {
        setExpandedPhaseKey(expandablePhases[index - 1].phase_key);
      }
      if (event.key === "ArrowRight" && index >= 0 && index < expandablePhases.length - 1) {
        setExpandedPhaseKey(expandablePhases[index + 1].phase_key);
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [expandedPhaseKey, viewMode, expandablePhases, setFocusMode]);

  return (
    <div className="space-y-4">
      <RolloutTimelineContextStrip detail={detail} />
      <RolloutEndorsementActions rolloutId={rolloutId} detail={detail} canManage={canManageRollout} />
      <RolloutTimelineActions rolloutId={rolloutId} detail={detail} canManage={canManageRollout} />
      <RolloutRfiActions rolloutId={rolloutId} detail={detail} canManage={canManageRollout} />

      <div className="space-y-3">
        {viewMode === "focus" && expandedPhase ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2">
            <p className="text-sm text-foreground">
              Focused on{" "}
              <span className="font-medium">
                <MilestonePhaseLabel phaseKey={expandedPhase.phase_key} label={expandedPhase.label} />
              </span>
              <span className="ml-2 hidden text-xs text-muted-foreground sm:inline">
                (← → switch work phases · Esc close)
              </span>
            </p>
            <div className="flex flex-wrap items-center gap-2">
              {prevExpandablePhase ? (
                <Button size="sm" variant="ghost" onClick={() => openPhaseWork(prevExpandablePhase.phase_key)}>
                  ← {prevExpandablePhase.label}
                </Button>
              ) : null}
              {nextExpandablePhase ? (
                <Button size="sm" variant="ghost" onClick={() => openPhaseWork(nextExpandablePhase.phase_key)}>
                  {nextExpandablePhase.label} →
                </Button>
              ) : null}
              <Button size="sm" variant="outline" onClick={() => setFocusMode(false)}>
                Show all phases ({phases.length})
              </Button>
            </div>
          </div>
        ) : null}

        {viewMode === "overview" && expandedPhase && phaseIsExpandable(expandedPhase.phase_key) ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground">
            <span>
              <span className="font-medium text-foreground">{expandedPhase.label}</span> work is open below.
            </span>
            <Button size="sm" variant="outline" onClick={() => setFocusMode(true)}>
              Focus this phase
            </Button>
          </div>
        ) : null}

        <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-8" aria-label="Expand" />
                <TableHead>Phase</TableHead>
                <TableHead>Owner</TableHead>
                <TableHead>Working days</TableHead>
                <TableHead>Target start</TableHead>
                <TableHead>Target end</TableHead>
                <TableHead>Actual date</TableHead>
                <TableHead>Progress</TableHead>
                <TableHead>Control gate</TableHead>
                <TableHead className="min-w-[160px]">Gate status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {tablePhases.map((phase) => (
                <PhaseSummaryRow
                  key={phase.id}
                  phase={phase}
                  detail={detail}
                  rolloutId={rolloutId}
                  canManageRollout={canManageRollout}
                  expanded={expandedPhaseKey === phase.phase_key}
                  onToggle={() => togglePhase(phase.phase_key)}
                />
              ))}
            </TableBody>
          </Table>
        </div>

        {expandedPhase && phaseHasPermitsPanel(expandedPhase.phase_key) ? (
          <>
            {expandedPhase.phase_key === "permitting" ? (
              <div className="mb-3">
                <RolloutBuildReadinessPanel
                  rolloutId={rolloutId}
                  detail={detail}
                  phaseKey={expandedPhase.phase_key}
                />
              </div>
            ) : null}
            <RolloutPhasePermitsPanel
              rolloutId={rolloutId}
              detail={detail}
              phaseKey={expandedPhase.phase_key}
              phaseLabel={expandedPhase.label}
              canManage={canManageRollout}
            />
          </>
        ) : null}

        {expandedPhase && phaseHasWorkPanel(expandedPhase.phase_key) ? (
          <RolloutPhaseWorkShell
            phaseKey={expandedPhase.phase_key}
            phaseLabel={expandedPhase.label}
            summary={phaseWorkSummary(expandedPhase.phase_key, detail)}
            headerActions={
              viewMode === "focus" ? (
                <Button size="sm" variant="ghost" onClick={closePhaseWork}>
                  Close work
                </Button>
              ) : (
                <Button size="sm" variant="ghost" onClick={() => setFocusMode(true)}>
                  Focus
                </Button>
              )
            }
          >
            {phaseWorkPanelKind(expandedPhase.phase_key) === "saq" ? (
              isEndorsementEstablished(detail) ? (
                <RolloutSaqWorkPanel
                  rolloutId={rolloutId}
                  detail={detail}
                  canManage={canSaq}
                  embedded
                />
              ) : (
                <p className="text-sm text-muted-foreground">
                  PMO must set the endorsement date above before SAQ can add site candidates.
                </p>
              )
            ) : null}
            {phaseWorkPanelKind(expandedPhase.phase_key) === "pre_assessment" ? (
              <RolloutPreAssessmentPanel rolloutId={rolloutId} detail={detail} />
            ) : null}
            {phaseWorkPanelKind(expandedPhase.phase_key) === "tssr" ? (
              <RolloutTssrPanel detail={detail} phaseKey={expandedPhase.phase_key} />
            ) : null}
            {phaseWorkPanelKind(expandedPhase.phase_key) === "close_out" ? (
              <RolloutCloseOutPanel
                rolloutId={rolloutId}
                detail={detail}
                phaseKey={expandedPhase.phase_key}
                canManage={canManageRollout}
              />
            ) : null}
            {phaseWorkPanelKind(expandedPhase.phase_key) === "cme" ? (
              <div className="space-y-4">
                {expandedPhase.phase_key === "pre_construction" || expandedPhase.phase_key === "skom" ? (
                  <RolloutBuildReadinessPanel
                    rolloutId={rolloutId}
                    detail={detail}
                    phaseKey={expandedPhase.phase_key}
                  />
                ) : null}
                {expandedPhase.phase_key === "construction" ? (
                  <RolloutConstructionPanel rolloutId={rolloutId} detail={detail} />
                ) : null}
                <RolloutCmeWorkPanel
                  rolloutId={rolloutId}
                  detail={detail}
                  canManage={canCme}
                  embedded
                  phaseKey={expandedPhase.phase_key}
                />
              </div>
            ) : null}
          </RolloutPhaseWorkShell>
        ) : null}

        <RolloutApprovalsActivityPanel rolloutId={rolloutId} detail={detail} />
      </div>
    </div>
  );
}

function PhaseSummaryRow({
  phase,
  detail,
  rolloutId,
  canManageRollout,
  expanded,
  onToggle,
}: {
  phase: RolloutTimelinePhase;
  detail: RolloutDetail;
  rolloutId: string;
  canManageRollout: boolean;
  expanded: boolean;
  onToggle: () => void;
}) {
  const expandable = phaseIsExpandable(phase.phase_key);
  const summary = phaseHasPermitsPanel(phase.phase_key)
    ? phasePermitsSummary(detail.permits ?? [], phase.phase_key)
    : phaseWorkSummary(phase.phase_key, detail);
  const rowId = `rollout-phase-${phase.phase_key}`;

  return (
    <TableRow
      id={rowId}
      className={
        expandable
          ? `cursor-pointer ${expanded ? "bg-primary/5 hover:bg-primary/10" : "hover:bg-muted/40"}`
          : undefined
      }
      onClick={expandable ? onToggle : undefined}
    >
      <TableCell className="w-8 p-2">
        {expandable ? (
          <button
            type="button"
            className="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-expanded={expanded}
            aria-label={expanded ? "Collapse phase work" : "Expand phase work"}
            onClick={(e) => {
              e.stopPropagation();
              onToggle();
            }}
          >
            {expanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
          </button>
        ) : (
          <span className="inline-block size-7" />
        )}
      </TableCell>
      <TableCell className="font-medium">
        <div className="space-y-1.5">
          <MilestonePhaseLabel phaseKey={phase.phase_key} label={phase.label} />
          <div className="flex flex-wrap items-center gap-1.5">
            <RolloutPhaseMetadataBadges phase={phase} />
            <PhaseReadinessBadge phase={phase} detail={detail} />
            {expandable && summary ? (
              <span className="text-xs font-normal text-muted-foreground">{summary}</span>
            ) : null}
          </div>
        </div>
      </TableCell>
      <TableCell className="capitalize">{phase.owner_role ?? "—"}</TableCell>
      <TableCell>
        {phase.working_day_start}–{phase.working_day_end}
      </TableCell>
      <TableCell>{phase.target_start_date ?? "—"}</TableCell>
      <TableCell>{phase.target_end_date ?? "—"}</TableCell>
      <TableCell className="text-muted-foreground">
        {phase.actual_end_date ?? phase.actual_start_date ?? "—"}
      </TableCell>
      <TableCell>
        <PhaseProgressBadge progress={phase.phase_progress} />
      </TableCell>
      <TableCell className="max-w-[180px] text-xs text-muted-foreground">
        {phase.gate_label ? (
          <span className="line-clamp-2">
            <GateLabelText text={phase.gate_label} />
          </span>
        ) : (
          "—"
        )}
      </TableCell>
      <TableCell onClick={(e) => e.stopPropagation()}>
        <RolloutTimelineGateSelect rolloutId={rolloutId} phase={phase} canManage={canManageRollout} />
      </TableCell>
    </TableRow>
  );
}

function PhaseProgressBadge({ progress }: { progress: string }) {
  const tone =
    progress === "completed"
      ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200"
      : progress === "active"
        ? "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
        : progress === "overdue"
          ? "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200"
          : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200";

  return <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${tone}`}>{progress}</span>;
}
