"use client";

import { Download } from "lucide-react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DashboardLineChart } from "@/components/dashboard/dashboard-line-chart";
import { ControlledDocumentSavedReportsCard } from "@/components/documents/controlled-document-saved-reports-card";
import { FilterSelect } from "@/components/forms/filter-select";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  downloadControlledDocumentReport,
  fetchControlledDocumentReport,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { filterBarClassName } from "@/lib/ui/field-control";
import { permissions } from "@/lib/rbac/permissions";
import {
  controlledReportHref,
  readControlledReportQuery,
} from "@/modules/documents/controlled-document-reports-query";

function shareOf(part: number, whole: number): string {
  if (whole <= 0) {
    return "0";
  }
  return (Math.round((part / whole) * 1000) / 10).toString();
}

export function ControlledDocumentReportsPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryState = readControlledReportQuery(searchParams);
  const [exportError, setExportError] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);

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
    placeholderData: keepPreviousData,
  });
  const data = reportQuery.data;
  const departments = data?.facets.departments ?? [];
  const documentTypes = data?.facets.document_types ?? [];
  const total = data?.counts.total ?? 0;
  const filtered = Boolean(
    queryState.department || queryState.documentType || queryState.status || queryState.file || queryState.period !== 90,
  );

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

  const departmentCount = data?.by_department.length ?? 0;

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-4">
        <WorkspacePageHeader
          title="Register reports"
          description={
            data
              ? `${data.from} to ${data.to}. Filters change every count and chart. Export downloads this list, up to 5,000 rows.`
              : "Filters change every count and chart. Export downloads this list, up to 5,000 rows."
          }
          actions={
            <div className="flex flex-wrap items-center justify-end gap-1.5">
              <Button type="button" variant="outline" size="sm" disabled={exporting} onClick={() => void onExport()}>
                <Download className="mr-1.5 h-4 w-4" />
                {exporting ? "Exporting…" : "Export CSV"}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
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

        <div className={filterBarClassName}>
          <div>
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
          <div>
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
          <div>
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
          <div>
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
          <div>
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
          {filtered ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() =>
                replace({
                  period: null,
                  "filter[department]": null,
                  "filter[document_type]": null,
                  "filter[status]": null,
                  "filter[file]": null,
                })
              }
            >
              Clear
            </Button>
          ) : null}
        </div>

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Metric label="In this period" value={data?.counts.total} detail={data ? `${data.from} – ${data.to}` : "Published in the window"} />
          <Metric
            label="Published"
            value={data?.counts.published}
            detail={data ? `${shareOf(data.counts.published, total)}% of this period` : "—"}
          />
          <Metric
            label="Obsolete"
            value={data?.counts.obsolete}
            detail={data ? `${shareOf(data.counts.obsolete, total)}% of this period` : "—"}
          />
          <Metric
            label="Aging"
            value={data?.aging_summary.documents}
            detail={
              data
                ? `${data.aging_summary.people} waiting · oldest ${data.aging_summary.oldest_days}d`
                : "People still waiting"
            }
          />
        </div>

        <div className="grid items-start gap-3 xl:grid-cols-2">
          <DashboardLineChart
            title="Publications over time"
            description="Published dates inside the selected period."
            headline="total"
            height={220}
            data={data?.publications ?? []}
            emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No publications in this period."}
          />
          <DashboardDonutChart
            title="Register mix"
            description="Each document once. Amber is pending, green is approved, slate is obsolete."
            showZeroInLegend
            height={200}
            data={[
              { key: "pending", label: "Pending", value: data?.mix.pending ?? 0, fill: DASHBOARD_CHART.warning },
              { key: "approved", label: "Approved", value: data?.mix.approved ?? 0, fill: DASHBOARD_CHART.success },
              { key: "obsolete", label: "Obsolete", value: data?.mix.obsolete ?? 0, fill: DASHBOARD_CHART.muted },
            ]}
            emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "Nothing in this period."}
          />
        </div>

        <div className="grid items-start gap-3 xl:grid-cols-5">
          <div className="xl:col-span-3">
            <DashboardBarChart
              title="By department"
              description="Publications in this period."
              data={data?.by_department ?? []}
              layout="horizontal"
              categoryWidth={220}
              height={Math.max(220, departmentCount * 28)}
              emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No departments in this period."}
            />
          </div>
          <div className="xl:col-span-2">
            <DashboardDonutChart
              title="By document type"
              description="Same period, split by type."
              className="h-auto"
              height={200}
              data={data?.by_document_type ?? []}
              emptyMessage={reportQuery.isError ? getErrorMessage(reportQuery.error) : "No document types in this period."}
            />
          </div>
        </div>

        <div className="grid items-start gap-3 xl:grid-cols-5">
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm xl:col-span-3">
            <div className="border-b border-border px-4 py-2.5">
              <h2 className="text-sm font-medium text-foreground">Aging</h2>
              <p className="text-xs text-muted-foreground">Days on the current round. A revision starts the count again.</p>
            </div>
            {(data?.aging ?? []).length === 0 ? (
              <p className="px-4 py-6 text-center text-sm text-muted-foreground">No reviewer or approver is waiting.</p>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Document</TableHead>
                      <TableHead>Person</TableHead>
                      <TableHead>Requestor department</TableHead>
                      <TableHead>Role</TableHead>
                      <TableHead className="text-right">Days</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data?.aging.map((row) => (
                      <TableRow key={`${row.submission_id}-${row.name}`}>
                        <TableCell className="font-medium">{row.document_code}</TableCell>
                        <TableCell>{row.name}</TableCell>
                        <TableCell>{row.requestor_department ?? "—"}</TableCell>
                        <TableCell>{row.role}</TableCell>
                        <TableCell className="text-right tabular-nums">{row.age_days}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>
          <div className="xl:col-span-2">
            <ControlledDocumentSavedReportsCard
              filters={queryState}
              onApply={(href) => router.replace(href, { scroll: false })}
            />
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}

function Metric({ label, value, detail }: { label: string; value: number | null | undefined; detail: string }) {
  return (
    <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-0.5 text-2xl font-medium tabular-nums text-foreground">{value ?? "—"}</p>
      <p className="mt-0.5 text-xs tabular-nums text-muted-foreground">{detail}</p>
    </div>
  );
}
