"use client";

import Link from "next/link";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DashboardLineChart } from "@/components/dashboard/dashboard-line-chart";
import { FilterSelect } from "@/components/forms/filter-select";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  fetchControlledDocumentOverview,
  type ControlledDocumentOverview,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { filterBarClassName } from "@/lib/ui/field-control";
import { permissions } from "@/lib/rbac/permissions";

function registerHref(params: Record<string, string>): string {
  const query = new URLSearchParams(params);
  const qs = query.toString();
  return qs ? `/documents/controlled/register?${qs}` : "/documents/controlled/register";
}

function shareOf(part: number, whole: number): string {
  if (whole <= 0) {
    return "0";
  }
  return (Math.round((part / whole) * 1000) / 10).toString();
}

function ShareCard({
  label,
  value,
  detail,
  href,
}: {
  label: string;
  value: number;
  detail: string;
  href: string;
}) {
  return (
    <Link href={href} className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors hover:bg-muted/40">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-medium tabular-nums text-foreground">{value}</p>
      <p className="mt-1 text-xs tabular-nums text-muted-foreground">{detail}</p>
    </Link>
  );
}

function readOverviewQuery(params: URLSearchParams) {
  const status = params.get("filter[status]") ?? "";
  return {
    department: params.get("filter[department]") ?? "",
    documentType: params.get("filter[document_type]") ?? "",
    status: status === "pending" || status === "published" || status === "obsolete" ? status : "",
    file: params.get("filter[file]") === "has" || params.get("filter[file]") === "missing" ? (params.get("filter[file]") ?? "") : "",
  };
}

function displayTitle(title: string): string {
  return title.replace(/_+/g, " ").replace(/\s+/g, " ").trim();
}

function formatDay(value: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }
  return date.toLocaleDateString();
}

export function ControlledDocumentOverviewPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryState = useMemo(() => readOverviewQuery(searchParams), [searchParams]);
  const overviewQuery = useQuery({
    queryKey: ["documents", "controlled", "overview", queryState],
    queryFn: () =>
      fetchControlledDocumentOverview({
        department: queryState.department || undefined,
        document_type: queryState.documentType || undefined,
        status: queryState.status || undefined,
        file: queryState.file || undefined,
      }),
    placeholderData: keepPreviousData,
  });
  const data = overviewQuery.data;
  const counts = data?.counts;
  const total = counts?.total ?? 0;
  const approved = counts?.published ?? 0;
  const facets = data?.facets;
  const filtered = Boolean(queryState.department || queryState.documentType || queryState.status || queryState.file);

  function replace(patch: Record<string, string | null>) {
    const next = new URLSearchParams(searchParams.toString());
    for (const [key, value] of Object.entries(patch)) {
      if (value === null || value === "") {
        next.delete(key);
      } else {
        next.set(key, value);
      }
    }
    const qs = next.toString();
    router.replace(qs ? `/documents/controlled?${qs}` : "/documents/controlled", { scroll: false });
  }

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-4">
        <WorkspacePageHeader
          title="Document register"
          description="Pending includes new documents and approved documents with a revision still in approval. Obsolete is a withdrawn document or one that still has an older revision."
          actions={
            <div className="flex flex-wrap items-center justify-end gap-1.5">
              <Button size="sm" render={<Link href="/documents/controlled/register" />}>
                Open register
              </Button>
              <Button size="sm" variant="outline" render={<Link href="/documents/controlled/reports" />}>
                Reports
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => void overviewQuery.refetch()}
                disabled={overviewQuery.isFetching}
              >
                {overviewQuery.isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
            </div>
          }
        />

        <div className={filterBarClassName}>
          <div>
            <FilterSelect
              id="cdr-overview-dept"
              label="Department"
              value={queryState.department}
              onChange={(value) => replace({ "filter[department]": value || null })}
            >
              <option value="">All departments</option>
              {(facets?.departments ?? []).map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </FilterSelect>
          </div>
          <div>
            <FilterSelect
              id="cdr-overview-type"
              label="Document type"
              value={queryState.documentType}
              onChange={(value) => replace({ "filter[document_type]": value || null })}
            >
              <option value="">All types</option>
              {(facets?.document_types ?? []).map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </FilterSelect>
          </div>
          <div>
            <FilterSelect
              id="cdr-overview-status"
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
              id="cdr-overview-file"
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
                  "filter[department]": null,
                  "filter[document_type]": null,
                  "filter[status]": null,
                  "filter[file]": null,
                })
              }
            >
              Clear filters
            </Button>
          ) : null}
        </div>

        {overviewQuery.isError ? (
          <div className="space-y-3 rounded-xl border border-border bg-card px-4 py-8 text-center">
            <p className="text-sm text-foreground">{getErrorMessage(overviewQuery.error)}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => void overviewQuery.refetch()}>
              Retry
            </Button>
          </div>
        ) : overviewQuery.isLoading || !data || !counts ? (
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {Array.from({ length: 4 }, (_, index) => (
              <div key={index} className="h-24 animate-pulse rounded-xl border border-border bg-muted/40" />
            ))}
          </div>
        ) : (
          <OverviewBody data={data} total={total} approved={approved} />
        )}
      </div>
    </PermissionGate>
  );
}

function OverviewBody({
  data,
  total,
  approved,
}: {
  data: ControlledDocumentOverview;
  total: number;
  approved: number;
}) {
  const counts = data.counts;
  const departments = data.departments ?? [];
  const mix = data.mix ?? { pending: counts.pending + counts.incoming, approved, obsolete: counts.obsolete };
  const agingSummary = data.aging_summary ?? { documents: 0, people: 0, oldest_days: 0 };
  const agingDetail = agingSummary.people === 0
    ? "No one is waiting"
    : `oldest ${agingSummary.oldest_days} ${agingSummary.oldest_days === 1 ? "day" : "days"} · ${agingSummary.people} ${agingSummary.people === 1 ? "person" : "people"}`;
  const departmentChart = {
    categories: departments.map((row) => row.label),
    series: [
      { key: "pending", label: "Pending", color: DASHBOARD_CHART.warning, values: departments.map((row) => row.pending + row.incoming) },
      { key: "approved", label: "Approved", color: DASHBOARD_CHART.success, values: departments.map((row) => row.approved) },
      { key: "obsolete", label: "Obsolete", color: DASHBOARD_CHART.muted, values: departments.map((row) => row.obsolete) },
    ],
  };

  return (
    <>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <ShareCard
          label="Pending"
          value={counts.pending + counts.incoming}
          detail={`${shareOf(counts.pending + counts.incoming, total)}% of the register`}
          href={registerHref({ "filter[status]": "pending" })}
        />
        <ShareCard
          label="Approved"
          value={approved}
          detail={`${shareOf(approved, total)}% of the register`}
          href={registerHref({ "filter[status]": "published" })}
        />
        <ShareCard
          label="Obsolete"
          value={counts.obsolete}
          detail={`${shareOf(counts.obsolete, total)}% of the register`}
          href={registerHref({ "filter[status]": "obsolete" })}
        />
        <ShareCard
          label="Aging"
          value={agingSummary.documents}
          detail={agingDetail}
          href={registerHref({ "filter[status]": "pending" })}
        />
      </div>
      <p className="text-xs text-muted-foreground">
        Pending includes documents that have never been published and approved documents waiting on a revision. Approved means published. Obsolete is the same set a version count would show: a withdrawn document, or a published document that still has an older revision. Aging is how many of those documents still have a reviewer or approver waiting.
      </p>

      <div className="grid gap-4 xl:grid-cols-2">
        <DashboardDonutChart
          title="Register mix"
          description="Each document is counted once. Pending is amber, approved is green, and obsolete is slate."
          showZeroInLegend
          data={[
            { key: "pending", label: "Pending", value: mix.pending, fill: DASHBOARD_CHART.warning },
            { key: "approved", label: "Approved", value: mix.approved, fill: DASHBOARD_CHART.success },
            { key: "obsolete", label: "Obsolete", value: mix.obsolete, fill: DASHBOARD_CHART.muted },
          ]}
          emptyMessage="No controlled documents yet."
        />
        <DashboardLineChart
          title="Approved over 30 days"
          description="The number is the total published in this window, not today alone."
          headline="total"
          data={data.published_over_time ?? []}
          emptyMessage="Nothing was published in the last 30 days."
        />
      </div>

      <DashboardBarChart
        title="Departments"
        description="Approved is the live document. Obsolete is each older revision, so both can show for the same document."
        data={departments.map((row) => ({ key: row.label, label: row.label, value: row.controlled }))}
        mode="stacked"
        layout="horizontal"
        categoryWidth={280}
        groups={departmentChart}
        emptyMessage="No departments on the register."
        height={Math.max(280, departments.length * 36)}
      />

      <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div className="border-b border-border px-4 py-3">
          <h2 className="text-sm font-medium text-foreground">By department</h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Controlled is the row total. Approved is the live document. Obsolete counts each older revision, so one approved document can have more than one obsolete revision.
          </p>
        </div>
        {departments.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-muted-foreground">No departments on the register.</p>
        ) : (
          <div className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Department</TableHead>
                <TableHead className="text-right">Controlled</TableHead>
                <TableHead className="text-right">Pending</TableHead>
                <TableHead className="text-right">Approved</TableHead>
                <TableHead className="text-right">Obsolete</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {departments.map((row) => (
                <TableRow key={row.label}>
                  <TableCell className="max-w-64 font-medium">
                    <Link
                      href={registerHref({ "filter[department]": row.key })}
                      className="text-foreground hover:underline"
                      title={row.label}
                    >
                      {row.label}
                    </Link>
                  </TableCell>
                  <TableCell className="text-right tabular-nums">{row.controlled}</TableCell>
                  <TableCell className="text-right tabular-nums">{row.pending + row.incoming}</TableCell>
                  <TableCell className="text-right tabular-nums">{row.approved}</TableCell>
                  <TableCell className="text-right tabular-nums">{row.obsolete}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          </div>
        )}
      </div>

      <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div className="border-b border-border px-4 py-3">
          <h2 className="text-sm font-medium text-foreground">Aging</h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Days since this person was asked on the current round. A revision starts the count again.
          </p>
        </div>
        {(data.aging ?? []).length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-muted-foreground">No reviewer or approver is waiting.</p>
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
                  <TableHead>Submitted</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.aging.map((row) => (
                  <TableRow key={`${row.submission_id}-${row.name}`}>
                    <TableCell>
                      <Link href={`/e-approval/submissions/${row.submission_id}`} className="font-medium text-foreground hover:underline">
                        {row.document_code}
                      </Link>
                    </TableCell>
                    <TableCell>{row.name}</TableCell>
                    <TableCell>{row.requestor_department ?? "—"}</TableCell>
                    <TableCell>{row.role}</TableCell>
                    <TableCell className="text-right tabular-nums">{row.age_days}</TableCell>
                    <TableCell className="tabular-nums">{formatDay(row.submitted_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </div>

      <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div className="border-b border-border px-4 py-3">
          <h2 className="text-sm font-medium text-foreground">Latest approved</h2>
          <p className="mt-0.5 text-xs text-muted-foreground">The eight most recently published documents.</p>
        </div>
        {data.recent.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-muted-foreground">Nothing has been approved yet.</p>
        ) : (
          <div className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Code</TableHead>
                <TableHead>Title</TableHead>
                <TableHead>Department</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-right">Revision</TableHead>
                <TableHead>Published</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.recent.map((row) => (
                <TableRow key={row.id}>
                  <TableCell>
                    <Link href={registerHref({ q: row.document_code })} className="font-medium text-foreground hover:underline">
                      {row.document_code}
                    </Link>
                  </TableCell>
                  <TableCell className="max-w-80 truncate" title={row.title}>
                    {displayTitle(row.title)}
                  </TableCell>
                  <TableCell>{row.department || "—"}</TableCell>
                  <TableCell>{row.document_type || "—"}</TableCell>
                  <TableCell className="text-right tabular-nums">{row.current_revision}</TableCell>
                  <TableCell className="tabular-nums">{formatDay(row.published_at)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          </div>
        )}
      </div>
    </>
  );
}
