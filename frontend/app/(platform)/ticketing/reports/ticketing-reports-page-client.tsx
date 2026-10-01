"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Download } from "lucide-react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DASHBOARD_CHART, kpiSeries } from "@/components/dashboard/dashboard-chart-utils";
import { FilterSelect } from "@/components/forms/filter-select";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { useTicketingDashboard } from "@/hooks/use-ticketing-dashboard";
import { getErrorMessage } from "@/lib/api/error";
import {
  downloadModuleListExportFile,
  fetchModuleListExports,
  type ModuleListExportRow,
} from "@/lib/api/modules/module-list-exports-api";
import { downloadTicketingTicketsExport, fetchTicketingMetadata } from "@/lib/api/modules/ticketing-api";
import { permissions } from "@/lib/rbac/permissions";
import { saveBlob } from "@/lib/ui/module-list-download";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type HubTab = "analytics" | "exports";
type ExportFormat = "csv" | "xlsx" | "html";

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" });
}

function exportStatusLabel(status: string): string {
  if (status === "completed") return "Ready";
  if (status === "processing") return "Processing";
  if (status === "queued") return "Queued";
  if (status === "failed") return "Failed";
  return status;
}

export function TicketingReportsPageClient() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [tab, setTab] = useState<HubTab>("analytics");
  const [status, setStatus] = useState("");
  const [priority, setPriority] = useState("");
  const [category, setCategory] = useState("");
  const [department, setDepartment] = useState("");
  const [scope, setScope] = useState<"all" | "mine" | "assigned">("all");
  const [format, setFormat] = useState<ExportFormat>("xlsx");
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const filters = useMemo(
    () => ({
      status: status || undefined,
      priority: priority || undefined,
      category: category || undefined,
      department: department || undefined,
      mine: scope === "mine" || undefined,
      assigned_me: scope === "assigned" || undefined,
    }),
    [category, department, priority, scope, status],
  );

  const dashboardQuery = useTicketingDashboard(filters);
  const metadataQuery = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
  });
  const historyQuery = useQuery({
    queryKey: ["module-list-exports", "ticketing"],
    queryFn: () => fetchModuleListExports(40),
    enabled: tab === "exports",
    refetchInterval: (query) => {
      const rows = (query.state.data ?? []).filter((row) => row.module === "ticketing");
      return rows.some((row) => row.status === "queued" || row.status === "processing") ? 4000 : false;
    },
  });

  const data = dashboardQuery.data;
  const categoryOptions =
    metadataQuery.data?.category_options ??
    (metadataQuery.data?.categories ?? []).map((id) => ({ id, label: id }));
  const departmentOptions = data?.filter_options?.departments ?? [];
  const hasFilters = Boolean(status || priority || category || department || scope !== "all");

  const queueSeries = useMemo(
    () =>
      kpiSeries(data?.kpis ?? [], ["open", "assigned_me", "urgent", "sla_at_risk", "resolved_week"]).filter(
        (row) => row.value > 0,
      ),
    [data?.kpis],
  );
  const statusSeries = useMemo(
    () =>
      (data?.status_breakdown ?? [])
        .filter((row) => row.count > 0)
        .map((row) => ({
          key: row.status,
          label: row.label,
          value: row.count,
          fill: DASHBOARD_CHART.brand,
        })),
    [data?.status_breakdown],
  );
  const prioritySeries = useMemo(
    () =>
      (data?.priority_breakdown ?? [])
        .filter((row) => row.count > 0)
        .map((row) => ({
          key: row.key,
          label: row.label,
          value: row.count,
          fill:
            row.key === "urgent"
              ? DASHBOARD_CHART.danger
              : row.key === "high"
                ? DASHBOARD_CHART.warning
                : row.key === "normal"
                  ? DASHBOARD_CHART.brand
                  : DASHBOARD_CHART.muted,
        })),
    [data?.priority_breakdown],
  );
  const categorySeries = useMemo(
    () =>
      (data?.by_category ?? [])
        .map((row) => ({
          key: row.category ?? "uncategorized",
          label: row.label,
          value: row.open + row.in_progress + row.resolved_7d,
          fill: DASHBOARD_CHART.brand,
        }))
        .filter((row) => row.value > 0)
        .slice(0, 8),
    [data?.by_category],
  );
  const departmentSeries = useMemo(
    () =>
      (data?.department_breakdown ?? [])
        .filter((row) => row.count > 0)
        .slice(0, 8)
        .map((row) => ({
          key: row.key,
          label: row.label,
          value: row.count,
          fill: DASHBOARD_CHART.brand,
        })),
    [data?.department_breakdown],
  );

  const ticketingExports = (historyQuery.data ?? []).filter((row) => row.module === "ticketing");

  const runExport = async () => {
    setExporting(true);
    setExportError(null);
    try {
      const result = await downloadTicketingTicketsExport({
        ...filters,
        format,
        async: format === "html" ? true : undefined,
      });
      if (result.mode === "async") {
        push({
          level: "info",
          title: "Export queued",
          message: result.message || "Download it from Recent exports when it is ready.",
        });
      } else {
        const stamp = new Date().toISOString().slice(0, 10);
        saveBlob(result.blob, `tickets-${stamp}.${format}`);
        push({
          level: "success",
          title: "Export ready",
          message: result.truncated
            ? `Downloaded the first ${result.maxRows.toLocaleString()} of ${result.totalRows.toLocaleString()} rows.`
            : "The file downloaded.",
        });
      }
      await queryClient.invalidateQueries({ queryKey: ["module-list-exports", "ticketing"] });
    } catch (error) {
      setExportError(getErrorMessage(error) || "Could not export tickets.");
    } finally {
      setExporting(false);
    }
  };

  const downloadHistory = async (row: ModuleListExportRow) => {
    setDownloadingId(row.id);
    try {
      await downloadModuleListExportFile(row);
    } catch (error) {
      push({
        level: "error",
        title: "Download failed",
        message: getErrorMessage(error) || "Could not download this export.",
      });
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingView]}>
      <div className="space-y-6">
        <TicketingPageHeader
          eyebrow={
            <Link href="/ticketing" className="hover:text-primary">
              Ticketing
            </Link>
          }
          title="Reports"
          description="Analytics for the ticket queue, and exports of the same filtered list."
        />

        <div className="inline-flex rounded-lg border border-border bg-card p-1" role="tablist" aria-label="Report sections">
          {(
            [
              { id: "analytics", label: "Analytics" },
              { id: "exports", label: "Exports" },
            ] as const
          ).map((item) => (
            <button
              key={item.id}
              type="button"
              role="tab"
              aria-selected={tab === item.id}
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

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="text-sm font-medium text-foreground">Filters</h2>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Applied to the charts and to the next export.
              </p>
            </div>
            {hasFilters ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => {
                  setStatus("");
                  setPriority("");
                  setCategory("");
                  setDepartment("");
                  setScope("all");
                }}
              >
                Clear filters
              </Button>
            ) : null}
          </div>
          <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <FilterSelect id="ticket-report-scope" label="Scope" value={scope} onChange={(value) => setScope(value as typeof scope)}>
              <option value="all">All visible tickets</option>
              <option value="mine">Requested by me</option>
              <option value="assigned">Assigned to me</option>
            </FilterSelect>
            <FilterSelect id="ticket-report-status" label="Status" value={status} onChange={setStatus}>
              <option value="">Any status</option>
              {(metadataQuery.data?.statuses ?? []).map((item) => (
                <option key={item} value={item}>
                  {item.replace(/_/g, " ")}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect id="ticket-report-priority" label="Priority" value={priority} onChange={setPriority}>
              <option value="">Any priority</option>
              {(metadataQuery.data?.priorities ?? []).map((item) => (
                <option key={item} value={item}>
                  {item.replace(/_/g, " ")}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect id="ticket-report-category" label="Category" value={category} onChange={setCategory}>
              <option value="">Any category</option>
              {categoryOptions.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.label}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect id="ticket-report-department" label="Department" value={department} onChange={setDepartment}>
              <option value="">Any department</option>
              {departmentOptions.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </FilterSelect>
          </div>
        </section>

        {tab === "analytics" ? (
          dashboardQuery.isLoading ? (
            <SectionCardSkeleton fields={4} />
          ) : dashboardQuery.isError ? (
            <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
              <p>{getErrorMessage(dashboardQuery.error) || "Could not load ticket analytics."}</p>
              <Button type="button" size="sm" variant="outline" className="mt-3" onClick={() => void dashboardQuery.refetch()}>
                Try again
              </Button>
            </div>
          ) : (
            <div className="space-y-4" role="tabpanel">
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {(data?.kpis ?? []).length === 0 ? (
                  <p className="text-sm text-muted-foreground">No ticket totals for these filters.</p>
                ) : (
                  data?.kpis.map((kpi) => (
                    <article key={kpi.key} className="rounded-xl border border-border bg-card p-4 shadow-sm">
                      <p className="text-xs font-medium text-muted-foreground">{kpi.label}</p>
                      <p className="mt-2 text-2xl font-semibold tabular-nums text-foreground">{kpi.value}</p>
                    </article>
                  ))
                )}
              </div>
              <div className="grid gap-4 lg:grid-cols-2">
                <DashboardBarChart
                  title="Ticket queue"
                  description="Open, assigned, urgent, at risk, and resolved this week"
                  data={queueSeries}
                  layout="horizontal"
                  emptyMessage="No ticket totals to chart."
                  height={220}
                />
                <DashboardDonutChart
                  title="By status"
                  description="Current status mix for the filtered tickets"
                  data={statusSeries}
                  emptyMessage="No status data to chart."
                  height={220}
                />
                <DashboardDonutChart
                  title="By priority"
                  description="Priority mix for the filtered tickets"
                  data={prioritySeries}
                  emptyMessage="No priority data to chart."
                  height={220}
                />
                <DashboardBarChart
                  title="Volume by category"
                  description="Open, in progress, and resolved in the last 7 days"
                  data={categorySeries}
                  layout="horizontal"
                  emptyMessage="No category volume yet."
                  height={240}
                />
              </div>
              <DashboardBarChart
                title="Volume by department"
                description="Requester department mix for the current filters"
                data={departmentSeries}
                layout="horizontal"
                emptyMessage="No department volume yet."
                height={240}
              />
              <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="border-b border-border px-4 py-3">
                  <h2 className="text-sm font-medium text-foreground">Category analytics</h2>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Active queue, SLA risk, and recent resolutions by category
                  </p>
                </div>
                {(data?.by_category ?? []).length === 0 ? (
                  <p className="px-4 py-8 text-center text-sm text-muted-foreground">No category data yet.</p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-[13px]">
                      <thead className="border-b border-border bg-muted text-xs font-medium text-muted-foreground">
                        <tr>
                          <th className="px-4 py-2">Category</th>
                          <th className="px-3 py-2 text-right">Open</th>
                          <th className="px-3 py-2 text-right">In progress</th>
                          <th className="px-3 py-2 text-right">Resolved 7d</th>
                          <th className="px-3 py-2 text-right">SLA risk</th>
                          <th className="px-4 py-2 text-right">Avg resolve</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(data?.by_category ?? []).map((row) => (
                          <tr key={row.category ?? "uncategorized"} className="border-b border-border last:border-0">
                            <td className="px-4 py-2 text-foreground">{row.label}</td>
                            <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">{row.open}</td>
                            <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">{row.in_progress}</td>
                            <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">{row.resolved_7d}</td>
                            <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">{row.sla_at_risk}</td>
                            <td className="px-4 py-2 text-right tabular-nums text-muted-foreground">
                              {row.avg_resolve_hours == null ? "—" : `${row.avg_resolve_hours}h`}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          )
        ) : (
          <div className="space-y-4" role="tabpanel">
            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <h2 className="text-sm font-medium text-foreground">Export tickets</h2>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Download the filtered queue as Excel or CSV. A printable HTML file is prepared in the background.
              </p>
              <div className="mt-3 flex flex-wrap items-end gap-3">
                <FilterSelect id="ticket-export-format" label="Format" value={format} onChange={(value) => setFormat(value as ExportFormat)}>
                  <option value="xlsx">Excel</option>
                  <option value="csv">CSV</option>
                  <option value="html">Printable HTML</option>
                </FilterSelect>
                <Button type="button" onClick={() => void runExport()} disabled={exporting}>
                  <Download className="mr-1.5 size-4" aria-hidden />
                  {exporting ? "Exporting…" : "Export"}
                </Button>
              </div>
              {exportError ? <p className="mt-3 text-sm text-destructive">{exportError}</p> : null}
            </section>

            <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
              <div className="border-b border-border px-4 py-3">
                <h2 className="text-sm font-medium text-foreground">Recent exports</h2>
                <p className="mt-0.5 text-xs text-muted-foreground">Ticket files you have queued or downloaded.</p>
              </div>
              {historyQuery.isLoading ? (
                <p className="px-4 py-6 text-sm text-muted-foreground">Loading export history…</p>
              ) : historyQuery.isError ? (
                <div className="px-4 py-4 text-sm text-destructive">
                  <p>{getErrorMessage(historyQuery.error) || "Could not load export history."}</p>
                  <Button type="button" size="sm" variant="outline" className="mt-3" onClick={() => void historyQuery.refetch()}>
                    Try again
                  </Button>
                </div>
              ) : ticketingExports.length === 0 ? (
                <p className="px-4 py-8 text-sm text-muted-foreground">No ticket exports yet.</p>
              ) : (
                <ul className="divide-y divide-border">
                  {ticketingExports.map((row) => (
                    <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-foreground">{row.filename || "Ticket export"}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {row.format.toUpperCase()} · {exportStatusLabel(row.status)} · {formatWhen(row.created_at)}
                          {row.exported_rows ? ` · ${row.exported_rows.toLocaleString()} rows` : ""}
                        </p>
                        {row.error_message ? <p className="mt-1 text-xs text-destructive">{row.error_message}</p> : null}
                      </div>
                      {row.status === "completed" && row.download?.url ? (
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          disabled={downloadingId === row.id}
                          onClick={() => void downloadHistory(row)}
                        >
                          {downloadingId === row.id ? "Downloading…" : "Download"}
                        </Button>
                      ) : null}
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
