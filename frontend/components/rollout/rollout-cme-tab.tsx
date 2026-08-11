"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import type { ColumnDef } from "@tanstack/react-table";

import { FormInput } from "@/components/forms/form-input";
import { FileUploadField } from "@/components/forms/file-upload-field";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RolloutFieldFormFooter } from "@/components/rollout/rollout-field-form-footer";
import { RolloutMediaPreview } from "@/components/rollout/rollout-media-preview";
import {
  PhaseWorkFormFieldSpan,
  PhaseWorkFormSection,
  phaseWorkSheetBodyClass,
  phaseWorkSheetContentClass,
  phaseWorkSheetFooterClass,
} from "@/components/rollout/phase-work-form-section";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { createClientDraftId, useRolloutDrafts } from "@/hooks/use-rollout-drafts";
import { getErrorMessage } from "@/lib/api/error";
import { createRolloutCmeReport } from "@/lib/api/modules/rollout-api";
import {
  cmePhaseWorkHint,
  filterCmeReportsForPhase,
  resolveTimelinePhaseId,
} from "@/lib/rollout/phase-work-panels";
import type { RolloutCmeReport, RolloutDetail, RolloutMediaLink } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

const cmeDailyReportColumns: ColumnDef<RolloutCmeReport>[] = [
  {
    accessorKey: "report_date",
    header: "Date",
    cell: ({ row }) => row.original.report_date ?? "—",
  },
  {
    accessorKey: "day_number",
    header: "Day #",
    cell: ({ row }) => row.original.day_number ?? "—",
  },
  {
    accessorKey: "physical_progress_pct",
    header: "Physical progress",
    cell: ({ row }) => (
      <div className="space-y-2">
        <span>{row.original.physical_progress_pct ?? "—"}%</span>
        <RolloutMediaPreview items={row.original.photo_links} />
      </div>
    ),
  },
];


type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  canManage: boolean;
  embedded?: boolean;
  phaseKey?: string;
  sectionTitle?: string;
};

/** CME daily reports — embeddable under timeline or standalone. */
export function RolloutCmeWorkPanel({
  rolloutId,
  detail,
  canManage,
  embedded = false,
  phaseKey,
  sectionTitle,
}: Props) {
  const reports = useMemo(() => {
    const list =
      phaseKey && detail
        ? filterCmeReportsForPhase(detail.cme_reports ?? [], phaseKey, detail.timeline_phases ?? [])
        : (detail?.cme_reports ?? []);
    return [...list].sort((a, b) => (a.report_date ?? "").localeCompare(b.report_date ?? ""));
  }, [phaseKey, detail]);

  const hint = phaseKey ? cmePhaseWorkHint(phaseKey) : sectionTitle;

  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const { queueDraft, isNetworkError } = useRolloutDrafts(rolloutId);
  const [showForm, setShowForm] = useState(false);
  const [reportDraftId, setReportDraftId] = useState(() => createClientDraftId());

  const [reportDate, setReportDate] = useState(new Date().toISOString().slice(0, 10));
  const [dayNumber, setDayNumber] = useState("");
  const [progressPct, setProgressPct] = useState("");
  const [planPct, setPlanPct] = useState("");
  const [workforce, setWorkforce] = useState("");
  const [weatherAm, setWeatherAm] = useState("");
  const [weatherPm, setWeatherPm] = useState("");
  const [manhoursToday, setManhoursToday] = useState("");
  const [manhoursCumulative, setManhoursCumulative] = useState("");
  const [qualityIssues, setQualityIssues] = useState("");
  const [safetyIncidents, setSafetyIncidents] = useState("");
  const [activitiesCompleted, setActivitiesCompleted] = useState("");
  const [activitiesPlanned, setActivitiesPlanned] = useState("");
  const [toolboxHeld, setToolboxHeld] = useState(true);
  const [photoLinks, setPhotoLinks] = useState<RolloutMediaLink[]>([]);

  const timelinePhaseId =
    phaseKey && detail ? resolveTimelinePhaseId(phaseKey, detail.timeline_phases ?? []) : undefined;

  const reportPayload = (clientDraftId?: string) => ({
    ...(clientDraftId ? { client_draft_id: clientDraftId } : {}),
    ...(timelinePhaseId ? { timeline_phase_id: timelinePhaseId } : {}),
    report_date: reportDate,
    day_number: dayNumber ? Number(dayNumber) : undefined,
    physical_progress_pct: progressPct ? Number(progressPct) : undefined,
    physical_progress_plan_pct: planPct ? Number(planPct) : undefined,
    workforce_count: workforce ? Number(workforce) : undefined,
    weather_am: weatherAm.trim() || undefined,
    weather_pm: weatherPm.trim() || undefined,
    manhours_today: manhoursToday ? Number(manhoursToday) : undefined,
    manhours_cumulative: manhoursCumulative ? Number(manhoursCumulative) : undefined,
    quality_issues: qualityIssues.trim() || undefined,
    safety_incidents: safetyIncidents.trim() || undefined,
    activities_completed: activitiesCompleted.trim() || undefined,
    activities_planned_tomorrow: activitiesPlanned.trim() || undefined,
    toolbox_meeting_held: toolboxHeld,
    photo_links: photoLinks.map(({ file_id, label }) => ({
      file_id,
      label: label ?? undefined,
    })),
  });

  const resetForm = () => {
    setPhotoLinks([]);
    setProgressPct("");
    setPlanPct("");
    setWorkforce("");
    setWeatherAm("");
    setWeatherPm("");
    setManhoursToday("");
    setManhoursCumulative("");
    setQualityIssues("");
    setSafetyIncidents("");
    setActivitiesCompleted("");
    setActivitiesPlanned("");
    setToolboxHeld(true);
  };

  const closeForm = () => {
    setShowForm(false);
    resetForm();
    setReportDraftId(createClientDraftId());
  };

  const openForm = () => {
    setReportDraftId(createClientDraftId());
    setShowForm(true);
  };

  const duplicateLastReport = () => {
    const last = reports[reports.length - 1];
    if (!last) {
      openForm();
      return;
    }
    setReportDate(new Date().toISOString().slice(0, 10));
    setDayNumber(last.day_number != null ? String(last.day_number) : "");
    setProgressPct(last.physical_progress_pct != null ? String(last.physical_progress_pct) : "");
    setPlanPct(last.physical_progress_plan_pct != null ? String(last.physical_progress_plan_pct) : "");
    setWorkforce(last.workforce_count != null ? String(last.workforce_count) : "");
    setWeatherAm(last.weather_am ?? "");
    setWeatherPm(last.weather_pm ?? "");
    setManhoursToday("");
    setManhoursCumulative(last.manhours_cumulative != null ? String(last.manhours_cumulative) : "");
    setQualityIssues(last.quality_issues ?? "");
    setSafetyIncidents(last.safety_incidents ?? "");
    setActivitiesCompleted(last.activities_completed ?? "");
    setActivitiesPlanned(last.activities_planned_tomorrow ?? "");
    setToolboxHeld(last.toolbox_meeting_held ?? true);
    setPhotoLinks([]);
    setReportDraftId(createClientDraftId());
    setShowForm(true);
  };

  const queueReportDraft = () => {
    queueDraft({
      client_draft_id: reportDraftId,
      kind: "cme_report",
      rolloutId,
      payload: reportPayload(reportDraftId),
      createdAt: new Date().toISOString(),
    });
    closeForm();
  };

  const mutation = useMutation({
    mutationFn: () => createRolloutCmeReport(rolloutId, reportPayload(reportDraftId)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
      closeForm();
      push({ level: "success", title: "CME daily report saved" });
    },
    onError: (error) => {
      if (isNetworkError(error) || !navigator.onLine) {
        queueReportDraft();
        return;
      }
      push({ level: "error", title: "Could not save report", message: getErrorMessage(error) });
    },
  });

  const submitReport = (e: React.FormEvent) => {
    e.preventDefault();
    if (!navigator.onLine) {
      queueReportDraft();
      return;
    }
    mutation.mutate();
  };

  const reportForm = (
    <form id="cme-daily-report-form" className="space-y-1" onSubmit={submitReport}>
      <PhaseWorkFormSection title="Report summary" description="Date and progress for this construction day.">
        <FormInput touchFriendly label="Report date" date value={reportDate} onChange={(e) => setReportDate(e.target.value)} />
        <FormInput
          touchFriendly
          label="Construction day #"
          type="number"
          inputMode="numeric"
          value={dayNumber}
          onChange={(e) => setDayNumber(e.target.value)}
        />
        <FormInput
          touchFriendly
          label="Physical progress %"
          type="number"
          inputMode="decimal"
          value={progressPct}
          onChange={(e) => setProgressPct(e.target.value)}
          placeholder="0–100"
        />
        <FormInput
          touchFriendly
          label="Plan progress %"
          type="number"
          inputMode="decimal"
          value={planPct}
          onChange={(e) => setPlanPct(e.target.value)}
          placeholder="0–100"
        />
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Site conditions" description="Weather, crew size, and manhours.">
        <FormInput
          touchFriendly
          label="Workforce count"
          type="number"
          inputMode="numeric"
          value={workforce}
          onChange={(e) => setWorkforce(e.target.value)}
        />
        <FormInput touchFriendly label="Weather AM" value={weatherAm} onChange={(e) => setWeatherAm(e.target.value)} />
        <FormInput touchFriendly label="Weather PM" value={weatherPm} onChange={(e) => setWeatherPm(e.target.value)} />
        <FormInput
          touchFriendly
          label="Manhours today"
          type="number"
          inputMode="numeric"
          value={manhoursToday}
          onChange={(e) => setManhoursToday(e.target.value)}
        />
        <FormInput
          touchFriendly
          label="Manhours cumulative"
          type="number"
          inputMode="numeric"
          value={manhoursCumulative}
          onChange={(e) => setManhoursCumulative(e.target.value)}
        />
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Safety & quality" columns="1">
        <label className="flex min-h-10 items-center gap-3 rounded-md border border-border bg-muted/20 px-3 py-2 text-sm">
          <Checkbox className="shrink-0" checked={toolboxHeld} onCheckedChange={(v) => setToolboxHeld(v === true)} />
          Toolbox meeting held
        </label>
        <label className="space-y-1.5">
          <span className="text-sm font-medium text-foreground">Quality issues</span>
          <textarea
            className="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
            value={qualityIssues}
            onChange={(e) => setQualityIssues(e.target.value)}
            placeholder="None, or describe defects / rework"
          />
        </label>
        <FormInput
          touchFriendly
          label="Safety incidents"
          value={safetyIncidents}
          onChange={(e) => setSafetyIncidents(e.target.value)}
          placeholder="None, or brief description"
        />
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Activities" columns="1">
        <label className="space-y-1.5">
          <span className="text-sm font-medium text-foreground">Activities completed</span>
          <textarea
            className="min-h-[96px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
            value={activitiesCompleted}
            onChange={(e) => setActivitiesCompleted(e.target.value)}
          />
        </label>
        <label className="space-y-1.5">
          <span className="text-sm font-medium text-foreground">Planned for tomorrow</span>
          <textarea
            className="min-h-[96px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
            value={activitiesPlanned}
            onChange={(e) => setActivitiesPlanned(e.target.value)}
          />
        </label>
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Site photos" columns="1">
        <FileUploadField
          rolloutId={rolloutId}
          context="cme_report"
          label="Photos"
          capture="environment"
          value={photoLinks}
          onChange={setPhotoLinks}
        />
      </PhaseWorkFormSection>

      {!embedded ? (
        <PhaseWorkFormFieldSpan className="pt-2">
          <RolloutFieldFormFooter
            submitLabel="Save report"
            isSubmitting={mutation.isPending}
            showSaveDraft
            onSaveDraft={queueReportDraft}
          />
        </PhaseWorkFormFieldSpan>
      ) : null}
    </form>
  );

  return (
    <div className="space-y-4">
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}

      {canManage ? (
        <div className="flex flex-wrap gap-2">
          <Button size={embedded ? "sm" : "lg"} className={embedded ? "" : "min-h-11"} variant="outline" onClick={openForm}>
            Add daily report
          </Button>
          {reports.length > 0 ? (
            <Button size={embedded ? "sm" : "lg"} className={embedded ? "" : "min-h-11"} variant="ghost" onClick={duplicateLastReport}>
              Copy last report
            </Button>
          ) : null}
        </div>
      ) : null}

      {!embedded && showForm && canManage ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">{reportForm}</div>
      ) : null}

      <div className="overflow-hidden rounded-lg border border-border bg-muted/20">
        <RegistryDataTableView
          columns={cmeDailyReportColumns}
          data={reports}
          getRowId={(row) => row.id}
          isEmpty={reports.length === 0}
          emptyMessage="No CME daily reports for this phase yet."
          scrollClassName="max-h-none"
          enableColumnVisibility
          columnVisibilityStorageKey="toweros.table.columns.project-one.rollout-cme"
        />
      </div>

      {embedded && canManage ? (
        <Sheet open={showForm} onOpenChange={(open) => (open ? openForm() : closeForm())}>
          <SheetContent side="right" className={phaseWorkSheetContentClass}>
            <SheetHeader className="shrink-0 border-b border-border">
              <SheetTitle>Daily CME report</SheetTitle>
              <SheetDescription>Capture field progress without leaving the timeline.</SheetDescription>
            </SheetHeader>
            {reports.length > 0 && showForm ? (
              <div className="border-b border-border px-4 py-2 sm:px-6">
                <Button type="button" size="sm" variant="ghost" onClick={duplicateLastReport}>
                  Prefill from last report
                </Button>
              </div>
            ) : null}
            <div className={phaseWorkSheetBodyClass}>{reportForm}</div>
            <div className={phaseWorkSheetFooterClass}>
              <RolloutFieldFormFooter
                submitLabel="Save report"
                isSubmitting={mutation.isPending}
                showSaveDraft
                onSaveDraft={queueReportDraft}
                formId="cme-daily-report-form"
              />
            </div>
          </SheetContent>
        </Sheet>
      ) : null}
    </div>
  );
}

/** @deprecated Use RolloutCmeWorkPanel — kept for backwards compatibility. */
export const RolloutCmeTab = RolloutCmeWorkPanel;
