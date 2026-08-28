"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Play, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EApprovalAnalyticsPanel } from "@/components/e-approval/e-approval-analytics-panel";
import { EApprovalExportReportCard } from "@/components/e-approval/e-approval-export-report-card";
import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { usePermission } from "@/hooks/use-permission";
import {
  deleteEApprovalReport,
  downloadEApprovalExportHistoryFile,
  fetchEApprovalExportHistory,
  fetchEApprovalReports,
  runEApprovalReport,
  updateEApprovalReport,
  type EApprovalReportDefinition,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function formatWhen(iso: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" });
}

const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as const;

type HubTab = "analytics" | "exports";

export function EApprovalReportsPageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const permissionsReady = useAuthStore((state) => state.permissionsReady);
  const canAudit = usePermission([permissions.eApprovalAuditView]);
  const canViewSubmissions = usePermission([permissions.eApprovalSubmissionsView]);
  const canAccess = canAudit || canViewSubmissions;
  const [tab, setTab] = useState<HubTab>("exports");
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);
  const [schedulingId, setSchedulingId] = useState<string | null>(null);

  useEffect(() => {
    if (!permissionsReady) return;
    if (!canAccess) {
      router.replace("/dashboard");
    }
  }, [canAccess, permissionsReady, router]);

  useEffect(() => {
    if (permissionsReady && canAudit) {
      setTab("analytics");
    }
  }, [canAudit, permissionsReady]);

  useEffect(() => {
    if (!canAudit && tab === "analytics") {
      setTab("exports");
    }
  }, [canAudit, tab]);

  const reportsQuery = useQuery({
    queryKey: ["e-approval", "reports"],
    queryFn: fetchEApprovalReports,
    enabled: tab === "exports",
  });

  const historyQuery = useQuery({
    queryKey: ["e-approval", "export-history"],
    queryFn: () => fetchEApprovalExportHistory(40),
    enabled: tab === "exports",
    refetchInterval: (query) => {
      const rows = query.state.data ?? [];
      const pending = rows.some((row) => row.status === "queued" || row.status === "processing");
      return pending ? 4000 : false;
    },
  });

  const invalidateHub = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["e-approval", "reports"] }),
      queryClient.invalidateQueries({ queryKey: ["e-approval", "export-history"] }),
    ]);
  };

  const runMutation = useMutation({
    mutationFn: async (report: EApprovalReportDefinition) => {
      const result = await runEApprovalReport(report.id);
      if (result.mode === "async") {
        return { mode: "async" as const, result, name: report.name };
      }
      const stamp = new Date().toISOString().slice(0, 10);
      const safe = report.name.toLowerCase().replace(/[^a-z0-9_-]+/g, "-") || "report";
      saveBlob(result.blob, `${safe}-${stamp}.${report.format}`);
      return { mode: "sync" as const, result, name: report.name };
    },
    onSuccess: async ({ mode, result, name }) => {
      setActionError(null);
      if (mode === "async") {
        setActionNotice(
          `“${name}” queued (${result.matchedRows.toLocaleString()} rows). Download from Recent exports when ready.`,
        );
      } else {
        setActionNotice(
          result.truncated
            ? `“${name}” exported (truncated at ${result.maxRows.toLocaleString()} of ${result.totalRows.toLocaleString()} rows).`
            : `“${name}” exported.`,
        );
      }
      await invalidateHub();
    },
    onError: (err) => {
      setActionNotice(null);
      setActionError(getErrorMessage(err));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteEApprovalReport(id),
    onSuccess: async () => {
      setActionError(null);
      setActionNotice("Report deleted.");
      await invalidateHub();
    },
    onError: (err) => {
      setActionNotice(null);
      setActionError(getErrorMessage(err));
    },
  });

  const scheduleMutation = useMutation({
    mutationFn: ({
      report,
      enabled,
      frequency,
      hour,
      dayOfWeek,
      recipients,
    }: {
      report: EApprovalReportDefinition;
      enabled: boolean;
      frequency: "daily" | "weekly";
      hour: number;
      dayOfWeek: number;
      recipients: string[];
    }) =>
      updateEApprovalReport(report.id, {
        schedule: {
          enabled,
          frequency,
          hour,
          day_of_week: dayOfWeek,
          recipients,
        },
      }),
    onSuccess: async () => {
      setActionError(null);
      setActionNotice("Schedule updated.");
      setSchedulingId(null);
      await invalidateHub();
    },
    onError: (err) => {
      setActionNotice(null);
      setActionError(getErrorMessage(err));
    },
  });

  return (
      <div className="space-y-6">
        <EApprovalPageHeader
          title="Reports"
          description={
            canAudit
              ? "Analytics, exports, saved report definitions, and download history."
              : "Export your own submissions. Attachment columns include download links."
          }
        />

        {!permissionsReady || !canAccess ? (
          <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
            Loading…
          </div>
        ) : (
          <>
        <div className="inline-flex rounded-lg border border-border bg-card p-1">
          {(
            [
              ...(canAudit ? [{ id: "analytics" as const, label: "Analytics" }] : []),
              { id: "exports" as const, label: "Exports" },
            ]
          ).map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={cn(
                "rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                tab === item.id
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:text-foreground",
              )}
            >
              {item.label}
            </button>
          ))}
        </div>

        {tab === "analytics" && canAudit ? <EApprovalAnalyticsPanel /> : null}

        {tab === "exports" ? (
          <>
            {actionError ? <p className="text-sm text-destructive">{actionError}</p> : null}
            {actionNotice ? (
              <p className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-foreground">
                {actionNotice}
              </p>
            ) : null}

            <EApprovalExportReportCard
              showSave
              onSaved={() => {
                void invalidateHub();
              }}
              onExported={() => {
                void invalidateHub();
              }}
            />

            <EApprovalSectionCard
              title="Saved reports"
              description="Re-run a saved configuration or enable a daily/weekly schedule."
            >
              {reportsQuery.isLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Spinner className="size-3.5" /> Loading saved reports…
                </div>
              ) : null}
              {reportsQuery.isError ? (
                <p className="text-sm text-destructive">{getErrorMessage(reportsQuery.error)}</p>
              ) : null}
              {!reportsQuery.isLoading && (reportsQuery.data?.length ?? 0) === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No saved reports yet. Configure an export above and click Save report.
                </p>
              ) : null}

              <ul className="divide-y divide-border rounded-lg border border-border">
                {(reportsQuery.data ?? []).map((report) => {
                  const schedule = report.schedule;
                  const isScheduling = schedulingId === report.id;
                  return (
                    <li key={report.id} className="space-y-3 px-3 py-3">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <p className="text-sm font-medium text-foreground">{report.name}</p>
                          <p className="mt-0.5 text-xs text-muted-foreground">
                            {report.format.toUpperCase()} · {report.layout.replace("_", " ")}
                            {schedule?.enabled
                              ? ` · ${schedule.frequency} at ${String(schedule.hour).padStart(2, "0")}:00`
                              : " · no schedule"}
                            {report.last_run_at ? ` · last run ${formatWhen(report.last_run_at)}` : ""}
                          </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setSchedulingId(isScheduling ? null : report.id)}
                          >
                            Schedule
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            onClick={() => runMutation.mutate(report)}
                            disabled={runMutation.isPending}
                          >
                            {runMutation.isPending ? (
                              <Spinner className="mr-1.5 size-3.5" />
                            ) : (
                              <Play className="mr-1.5 size-3.5" aria-hidden />
                            )}
                            Run
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              if (window.confirm(`Delete “${report.name}”?`)) {
                                deleteMutation.mutate(report.id);
                              }
                            }}
                            disabled={deleteMutation.isPending}
                          >
                            <Trash2 className="size-3.5" aria-hidden />
                          </Button>
                        </div>
                      </div>

                      {isScheduling ? (
                        <ScheduleEditor
                          report={report}
                          busy={scheduleMutation.isPending}
                          onCancel={() => setSchedulingId(null)}
                          onSave={(payload) => scheduleMutation.mutate({ report, ...payload })}
                        />
                      ) : null}
                    </li>
                  );
                })}
              </ul>
            </EApprovalSectionCard>

            <EApprovalSectionCard
              title="Recent exports"
              description="Manual and scheduled export runs for your account."
            >
              {historyQuery.isLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Spinner className="size-3.5" /> Loading history…
                </div>
              ) : null}
              {historyQuery.isError ? (
                <p className="text-sm text-destructive">{getErrorMessage(historyQuery.error)}</p>
              ) : null}
              {!historyQuery.isLoading && (historyQuery.data?.length ?? 0) === 0 ? (
                <p className="text-sm text-muted-foreground">No exports yet.</p>
              ) : null}

              {(historyQuery.data?.length ?? 0) > 0 ? (
                <div className="overflow-x-auto rounded-lg border border-border">
                  <table className="min-w-full text-left text-sm">
                    <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                      <tr>
                        <th className="px-3 py-2 font-medium">When</th>
                        <th className="px-3 py-2 font-medium">Name</th>
                        <th className="px-3 py-2 font-medium">Status</th>
                        <th className="px-3 py-2 font-medium">Format</th>
                        <th className="px-3 py-2 font-medium">Rows</th>
                        <th className="px-3 py-2 font-medium">Source</th>
                        <th className="px-3 py-2 font-medium">Download</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {(historyQuery.data ?? []).map((row) => (
                        <tr key={row.id}>
                          <td className="px-3 py-2 text-muted-foreground">{formatWhen(row.created_at)}</td>
                          <td className="px-3 py-2 font-medium text-foreground">
                            {row.name ?? "Export"}
                            {row.truncated ? (
                              <span className="ml-2 text-xs font-normal text-amber-700 dark:text-amber-400">
                                truncated
                              </span>
                            ) : null}
                            {row.error_message ? (
                              <p className="mt-0.5 text-xs font-normal text-destructive">{row.error_message}</p>
                            ) : null}
                          </td>
                          <td className="px-3 py-2 capitalize text-muted-foreground">{row.status}</td>
                          <td className="px-3 py-2 uppercase text-muted-foreground">{row.format}</td>
                          <td className="px-3 py-2 text-muted-foreground">
                            {row.exported_rows.toLocaleString()}
                            {row.matched_rows > row.exported_rows
                              ? ` / ${row.matched_rows.toLocaleString()}`
                              : ""}
                          </td>
                          <td className="px-3 py-2 capitalize text-muted-foreground">{row.triggered_by}</td>
                          <td className="px-3 py-2">
                            {row.status === "completed" && row.download ? (
                              <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-7"
                                onClick={async () => {
                                  try {
                                    await downloadEApprovalExportHistoryFile(row);
                                  } catch (err) {
                                    setActionError(getErrorMessage(err));
                                  }
                                }}
                              >
                                <Download className="mr-1 size-3.5" aria-hidden />
                                File
                              </Button>
                            ) : row.status === "queued" || row.status === "processing" ? (
                              <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Spinner className="size-3" /> Preparing…
                              </span>
                            ) : (
                              <span className="text-xs text-muted-foreground">—</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}
            </EApprovalSectionCard>
          </>
        ) : null}
          </>
        )}
      </div>
  );
}

function ScheduleEditor({
  report,
  busy,
  onCancel,
  onSave,
}: {
  report: EApprovalReportDefinition;
  busy: boolean;
  onCancel: () => void;
  onSave: (payload: {
    enabled: boolean;
    frequency: "daily" | "weekly";
    hour: number;
    dayOfWeek: number;
    recipients: string[];
  }) => void;
}) {
  const existing = report.schedule;
  const [enabled, setEnabled] = useState(existing?.enabled ?? false);
  const [frequency, setFrequency] = useState<"daily" | "weekly">(existing?.frequency ?? "daily");
  const [hour, setHour] = useState(String(existing?.hour ?? 8));
  const [dayOfWeek, setDayOfWeek] = useState(String(existing?.day_of_week ?? 1));
  const [recipients, setRecipients] = useState((existing?.recipients ?? []).join(", "));

  return (
    <div className="grid gap-3 rounded-lg border border-dashed border-border bg-card p-3 sm:grid-cols-2 lg:grid-cols-4">
      <div className="space-y-1.5">
        <Label htmlFor={`sched-enabled-${report.id}`}>Enabled</Label>
        <Select
          id={`sched-enabled-${report.id}`}
          value={enabled ? "1" : "0"}
          onChange={(event) => setEnabled(event.target.value === "1")}
        >
          <option value="0">Off</option>
          <option value="1">On</option>
        </Select>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor={`sched-freq-${report.id}`}>Frequency</Label>
        <Select
          id={`sched-freq-${report.id}`}
          value={frequency}
          onChange={(event) => setFrequency(event.target.value as "daily" | "weekly")}
        >
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
        </Select>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor={`sched-hour-${report.id}`}>Hour (0–23)</Label>
        <Input
          id={`sched-hour-${report.id}`}
          type="number"
          min={0}
          max={23}
          value={hour}
          onChange={(event) => setHour(event.target.value)}
        />
      </div>
      {frequency === "weekly" ? (
        <div className="space-y-1.5">
          <Label htmlFor={`sched-dow-${report.id}`}>Day</Label>
          <Select
            id={`sched-dow-${report.id}`}
            value={dayOfWeek}
            onChange={(event) => setDayOfWeek(event.target.value)}
          >
            {DAY_LABELS.map((label, index) => (
              <option key={label} value={String(index)}>
                {label}
              </option>
            ))}
          </Select>
        </div>
      ) : null}
      <div className="space-y-1.5 sm:col-span-2 lg:col-span-4">
        <Label htmlFor={`sched-recipients-${report.id}`}>Recipients (comma-separated emails)</Label>
        <Input
          id={`sched-recipients-${report.id}`}
          value={recipients}
          placeholder="ops@example.com, finance@example.com"
          onChange={(event) => setRecipients(event.target.value)}
        />
        <p className="text-xs text-muted-foreground">
          Scheduled runs persist downloadable files (or queue large ones). Delivery hooks can attach to these logs.
        </p>
      </div>
      <div className="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
        <Button
          type="button"
          size="sm"
          disabled={busy}
          onClick={() =>
            onSave({
              enabled,
              frequency,
              hour: Math.max(0, Math.min(23, Number(hour) || 0)),
              dayOfWeek: Math.max(0, Math.min(6, Number(dayOfWeek) || 0)),
              recipients: recipients
                .split(",")
                .map((item) => item.trim())
                .filter(Boolean),
            })
          }
        >
          {busy ? <Spinner className="mr-1.5 size-3.5" /> : <Download className="mr-1.5 size-3.5" aria-hidden />}
          Save schedule
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={onCancel} disabled={busy}>
          Cancel
        </Button>
      </div>
    </div>
  );
}
