"use client";

import { Download } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useMemo, useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardLineChart } from "@/components/dashboard/dashboard-line-chart";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ControlledDocumentSavedReportsCard } from "@/components/documents/controlled-document-saved-reports-card";
import { FilterSelect } from "@/components/forms/filter-select";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import { useDashboardCustomizeMode, useDashboardLayoutPrefs } from "@/hooks/use-dashboard-layout-prefs";
import {
  downloadControlledDocumentReport,
  fetchControlledDocumentReport,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import {
  CONTROLLED_REPORT_DEFAULT_IDS,
  CONTROLLED_REPORT_WIDGETS,
  CONTROLLED_REPORTS_LAYOUT_KEY,
  controlledReportCatalog,
} from "@/modules/documents/controlled-document-reports-board";
import {
  controlledReportHref,
  readControlledReportQuery,
} from "@/modules/documents/controlled-document-reports-query";

export function ControlledDocumentReportsPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryState = readControlledReportQuery(searchParams);
  const [exportError, setExportError] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);
  const catalog = useMemo(() => controlledReportCatalog(), []);
  const {
    layout,
    setLayout,
    tenantDefault,
    publishTenantDefault,
    resetToTenantDefault,
    flushPersonalPersist,
  } = useDashboardLayoutPrefs(CONTROLLED_REPORTS_LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode({ onExitEdit: flushPersonalPersist });
  const boardHandlers = useDashboardBoardLayoutHandlers(layout, setLayout, CONTROLLED_REPORT_DEFAULT_IDS);

  const reportParams = {
    period: queryState.period,
    department: queryState.department || undefined,
    document_type: queryState.documentType || undefined,
    status: queryState.status || undefined,
    review: queryState.review || undefined,
    file: queryState.file || undefined,
  };

  const reportQuery = useQuery({
    queryKey: ["documents", "controlled", "reports", queryState],
    queryFn: () => fetchControlledDocumentReport(reportParams),
  });
  const data = reportQuery.data;
  const departments = data?.facets.departments ?? [];
  const documentTypes = data?.facets.document_types ?? [];

  function replace(patch: Record<string, string | null>) {
    router.replace(controlledReportHref(searchParams, patch), { scroll: false });
  }

  async function onExport() {
    setExporting(true);
    setExportError(null);
    try {
      const blob = await downloadControlledDocumentReport(reportParams);
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = "controlled-document-register.csv";
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      setExportError(getErrorMessage(error));
    } finally {
      setExporting(false);
    }
  }

  const widgets: DashboardWidgetDef[] = (() => {
    const byId = new Map(CONTROLLED_REPORT_WIDGETS.map((widget) => [widget.id, widget]));
    const filters = byId.get("cdr_filters");
    const counts = byId.get("cdr_counts");
    const publications = byId.get("cdr_publications");
    const department = byId.get("cdr_department");
    const type = byId.get("cdr_type");
    const review = byId.get("cdr_review");
    const saved = byId.get("cdr_saved");

    return [
      {
        id: "cdr_filters",
        label: filters?.label ?? "Filters",
        removable: false,
        hideable: false,
        defaultSpan: "full",
        render: () => (
          <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4">
            <div className="w-full min-w-[140px] sm:w-40">
              <FilterSelect
                id="cdr-report-period"
                label="Period"
                value={String(queryState.period)}
                onChange={(value) => replace({ period: value === "90" ? null : value })}
              >
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="365">Last 365 days</option>
              </FilterSelect>
            </div>
            <div className="w-full min-w-[140px] sm:w-44">
              <FilterSelect
                id="cdr-report-dept"
                label="Department"
                value={queryState.department}
                onChange={(value) => replace({ "filter[department]": value || null })}
              >
                <option value="">All departments</option>
                {departments.map((item) => (
                  <option key={item} value={item}>
                    {item}
                  </option>
                ))}
              </FilterSelect>
            </div>
            <div className="w-full min-w-[140px] sm:w-44">
              <FilterSelect
                id="cdr-report-type"
                label="Document type"
                value={queryState.documentType}
                onChange={(value) => replace({ "filter[document_type]": value || null })}
              >
                <option value="">All types</option>
                {documentTypes.map((item) => (
                  <option key={item} value={item}>
                    {item}
                  </option>
                ))}
              </FilterSelect>
            </div>
            <div className="w-full min-w-[140px] sm:w-40">
              <FilterSelect
                id="cdr-report-status"
                label="Status"
                value={queryState.status}
                onChange={(value) => replace({ "filter[status]": value || null })}
              >
                <option value="">Any status</option>
                <option value="pending">Pending</option>
                <option value="published">Published</option>
                <option value="obsolete">Obsolete</option>
              </FilterSelect>
            </div>
            <div className="w-full min-w-[140px] sm:w-44">
              <FilterSelect
                id="cdr-report-review"
                label="Review"
                value={queryState.review}
                onChange={(value) => replace({ "filter[review]": value || null })}
              >
                <option value="">Any review date</option>
                <option value="overdue">Overdue</option>
                <option value="due_30">Due in 30 days</option>
                <option value="due_90">Due in 90 days</option>
                <option value="none">No review date</option>
              </FilterSelect>
            </div>
            <div className="w-full min-w-[140px] sm:w-40">
              <FilterSelect
                id="cdr-report-file"
                label="File"
                value={queryState.file}
                onChange={(value) => replace({ "filter[file]": value || null })}
              >
                <option value="">Any file</option>
                <option value="has">Has file</option>
                <option value="missing">Missing file</option>
              </FilterSelect>
            </div>
          </div>
        ),
      },
      {
        id: "cdr_counts",
        label: counts?.label ?? "Counts",
        removable: true,
        defaultSpan: "full",
        render: () => (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {data
                ? `${data.from} to ${data.to} · ${data.counts.total.toLocaleString()} documents`
                : reportQuery.isError
                  ? getErrorMessage(reportQuery.error)
                  : "Loading the report…"}
            </p>
            <div className="grid gap-3 sm:grid-cols-3">
              <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p className="text-xs text-muted-foreground">In this period</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">{data?.counts.total ?? "—"}</p>
              </div>
              <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p className="text-xs text-muted-foreground">Published</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">{data?.counts.published ?? "—"}</p>
              </div>
              <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p className="text-xs text-muted-foreground">Obsolete</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">{data?.counts.obsolete ?? "—"}</p>
              </div>
            </div>
            {(data?.review ?? []).length > 0 ? (
              <Card>
                <CardHeader className="gap-1">
                  <CardTitle className="text-base font-medium">Review horizon</CardTitle>
                  <p className="text-xs text-muted-foreground">Exclusive buckets</p>
                </CardHeader>
                <CardContent className="pt-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Window</TableHead>
                        <TableHead className="text-right">Count</TableHead>
                        <TableHead className="text-right">Share</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.review.map((row) => (
                        <TableRow key={row.key}>
                          <TableCell>{row.label}</TableCell>
                          <TableCell className="text-right tabular-nums">{row.value.toLocaleString()}</TableCell>
                          <TableCell className="text-right tabular-nums">
                            {sharePct(
                              row.value,
                              data.review.reduce((sum, item) => sum + item.value, 0),
                            )}
                            %
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            ) : null}
          </div>
        ),
      },
      {
        id: "cdr_publications",
        label: publications?.label ?? "Publications over time",
        removable: true,
        defaultSpan: "half",
        render: () => (
          <DashboardLineChart
            title="Publications over time"
            description="Documents whose published date falls in the period."
            data={data?.publications ?? []}
            emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No publications in this period."}
          />
        ),
      },
      {
        id: "cdr_department",
        label: department?.label ?? "By department",
        removable: true,
        defaultSpan: "half",
        render: () => (
          <DashboardBarChart
            title="By department"
            data={data?.by_department ?? []}
            emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No departments in this period."}
          />
        ),
      },
      {
        id: "cdr_type",
        label: type?.label ?? "By document type",
        removable: true,
        defaultSpan: "half",
        render: () => (
          <DashboardDonutChart
            title="By document type"
            data={data?.by_document_type ?? []}
            emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No document types in this period."}
          />
        ),
      },
      {
        id: "cdr_review",
        label: review?.label ?? "Review horizon",
        removable: true,
        defaultSpan: "full",
        render: () => (
          <DashboardBarChart
            title="Review horizon"
            description="Overdue, the next 30 days, and days 31 to 90. These buckets do not overlap."
            data={data?.review ?? []}
            layout="horizontal"
            emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No review dates in this period."}
          />
        ),
      },
      {
        id: "cdr_saved",
        label: saved?.label ?? "Saved reports",
        removable: true,
        defaultSpan: "full",
        render: () => (
          <ControlledDocumentSavedReportsCard
            filters={queryState}
            onApply={(href) => router.replace(href, { scroll: false })}
          />
        ),
      },
    ];
  })();

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-6">
        <WorkspacePageHeader
          title="Register reports"
          description="Publications in the selected period, with the same department, type, status, review, and file filters as the register. Export downloads that filtered list, up to 5,000 rows."
          actions={
            <div className="flex flex-wrap items-center justify-end gap-1.5">
              <DashboardLayoutToolbar
                widgets={widgets}
                catalogModule="workspace"
                layout={layout}
                editing={editing}
                onEditingChange={setEditing}
                onChange={setLayout}
                defaultEnabledIds={CONTROLLED_REPORT_DEFAULT_IDS}
                bindableCatalog={catalog}
                hasTenantDefault={Boolean(tenantDefault)}
                onPublishTenantDefault={publishTenantDefault}
                onResetToTenantDefault={resetToTenantDefault}
              />
              <Button type="button" variant="outline" size="sm" disabled={exporting} onClick={() => void onExport()}>
                <Download className="mr-1.5 h-4 w-4" />
                {exporting ? "Exporting…" : "Export CSV"}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-7 px-3 text-[11px]"
                onClick={() => void reportQuery.refetch()}
                disabled={reportQuery.isFetching}
              >
                {reportQuery.isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
            </div>
          }
        />

        {exportError ? <p className="text-sm text-destructive">{exportError}</p> : null}
        {reportQuery.isError ? (
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3">
            <p className="text-sm text-foreground">{getErrorMessage(reportQuery.error)}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => void reportQuery.refetch()}>
              Retry
            </Button>
          </div>
        ) : null}

        <DashboardWidgetBoard
          widgets={widgets}
          order={layout.widgetOrder}
          hiddenIds={layout.hiddenWidgetIds}
          enabledIds={layout.enabledWidgetIds}
          layoutPrefs={layout}
          editing={editing}
          addableCatalog={catalog}
          {...boardHandlers}
        />
      </div>
    </PermissionGate>
  );
}

function sharePct(count: number, total: number): number {
  return total > 0 ? Math.round((count / total) * 100) : 0;
}
