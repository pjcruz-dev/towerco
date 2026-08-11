"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DashboardLineChart } from "@/components/dashboard/dashboard-line-chart";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import {
  fetchEApprovalAnalytics,
  type EApprovalAnalyticsResponse,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";

function defaultRange(): { from: string; to: string } {
  const to = new Date();
  const from = new Date();
  from.setDate(to.getDate() - 29);
  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
  };
}

function toChartData(
  rows: Array<{ key: string; label: string; value: number }>,
): Array<{ key: string; label: string; value: number }> {
  return rows.map((row) => ({ key: row.key, label: row.label, value: row.value }));
}

export function EApprovalAnalyticsPanel() {
  const defaults = useMemo(() => defaultRange(), []);
  const [from, setFrom] = useState(defaults.from);
  const [to, setTo] = useState(defaults.to);
  const [applied, setApplied] = useState(defaults);

  const query = useQuery({
    queryKey: ["e-approval", "analytics", applied.from, applied.to],
    queryFn: () => fetchEApprovalAnalytics({ from: applied.from, to: applied.to }),
  });

  const data = query.data;

  return (
    <div className="space-y-4">
      <EApprovalSectionCard
        title="Analytics"
        description="Operational volume, cycle time, bottlenecks, and SLA aging for the selected period."
        actions={
          <div className="flex flex-wrap items-end gap-2">
            <div className="space-y-1">
              <Label htmlFor="analytics-from" className="text-xs">
                From
              </Label>
              <DatePicker
                id="analytics-from"
                value={from}
                onChange={setFrom}
                className="h-8 w-[140px]"
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor="analytics-to" className="text-xs">
                To
              </Label>
              <DatePicker
                id="analytics-to"
                value={to}
                onChange={setTo}
                className="h-8 w-[140px]"
              />
            </div>
            <Button
              type="button"
              size="sm"
              onClick={() => setApplied({ from, to })}
              disabled={query.isFetching}
            >
              {query.isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
              Apply
            </Button>
          </div>
        }
      >
        {query.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(query.error)}</p>
        ) : null}

        {query.isLoading && !data ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Spinner className="size-3.5" /> Loading analytics…
          </div>
        ) : null}

        {data ? <AnalyticsBody data={data} /> : null}
      </EApprovalSectionCard>
    </div>
  );
}

function AnalyticsBody({ data }: { data: EApprovalAnalyticsResponse }) {
  return (
    <div className="space-y-4">
      <KpiStrip
        items={data.kpis.map((kpi) => ({
          key: kpi.key,
          label: kpi.label,
          value: kpi.value,
          change: kpi.change ?? undefined,
          tone: (kpi.tone as "neutral" | "success" | "warning" | "danger") ?? "neutral",
        }))}
      />

      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {data.kpis
          .filter((kpi) => kpi.href)
          .map((kpi) => (
            <Link
              key={`link-${kpi.key}`}
              href={kpi.href!}
              className="rounded-lg border border-border bg-muted/20 px-3 py-2 text-xs font-medium text-foreground hover:border-primary/30 hover:bg-muted/40"
            >
              Drill into {kpi.label}
            </Link>
          ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardLineChart
          title="Submissions over time"
          description={`${data.period.from} → ${data.period.to}`}
          data={toChartData(data.submissions_over_time)}
          emptyMessage="No submissions in this period."
          height={220}
        />
        <div className="space-y-2">
          <DashboardDonutChart
            title="By status"
            description="Submission status mix"
            data={toChartData(data.by_status)}
            emptyMessage="No status breakdown."
            height={220}
          />
          {data.by_status.length > 0 ? (
            <ul className="flex flex-wrap gap-2">
              {data.by_status.map((row) =>
                row.href ? (
                  <li key={row.key}>
                    <Link
                      href={row.href}
                      className="rounded-full border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-primary/40 hover:text-foreground"
                    >
                      {row.label} ({row.value})
                    </Link>
                  </li>
                ) : null,
              )}
            </ul>
          ) : null}
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-2">
          <DashboardBarChart
            title="Top forms"
            description="Highest volume in the selected period"
            data={toChartData(data.top_forms)}
            layout="horizontal"
            emptyMessage="No form volume yet."
            height={240}
          />
          {data.top_forms.length > 0 ? (
            <ul className="flex flex-wrap gap-2">
              {data.top_forms.slice(0, 5).map((row) =>
                row.href ? (
                  <li key={row.key}>
                    <Link
                      href={row.href}
                      className="rounded-full border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-primary/40 hover:text-foreground"
                    >
                      {row.label} ({row.value})
                    </Link>
                  </li>
                ) : null,
              )}
            </ul>
          ) : null}
        </div>
        <DashboardBarChart
          title="Approval aging"
          description="Pending approvals by age vs SLA"
          data={toChartData(data.aging)}
          emptyMessage="No pending approvals."
          height={240}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardBarChart
          title="Bottleneck steps"
          description="Pending count by workflow step"
          data={data.bottlenecks.map((row) => ({
            key: row.key,
            label: `${row.label} (${row.avg_age_hours}h avg)`,
            value: row.value,
          }))}
          layout="horizontal"
          emptyMessage="No pending step bottlenecks."
          height={220}
        />
        <DashboardBarChart
          title="Approver load"
          description="Open approvals by assignee"
          data={toChartData(data.approver_load)}
          layout="horizontal"
          emptyMessage="No pending approver load."
          height={220}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <EApprovalSectionCard title="Cycle time" description="Average durations for the period.">
          <ul className="space-y-2">
            {data.cycle_times.map((row) => (
              <li
                key={row.key}
                className="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm"
              >
                <span className="text-muted-foreground">{row.label}</span>
                <span className="font-medium text-foreground">
                  {row.value} <span className="text-xs font-normal text-muted-foreground">{row.unit}</span>
                </span>
              </li>
            ))}
          </ul>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Rejection reasons"
          description="Top free-text remarks on rejected approvals."
        >
          {(data.rejection_reasons ?? []).length === 0 ? (
            <p className="text-sm text-muted-foreground">No rejection remarks in this period.</p>
          ) : (
            <ul className="divide-y divide-border rounded-lg border border-border">
              {data.rejection_reasons.map((row) => (
                <li key={row.key} className="flex items-start justify-between gap-3 px-3 py-2 text-sm">
                  <span className="text-foreground">{row.label}</span>
                  <span className="shrink-0 font-medium text-muted-foreground">{row.value}</span>
                </li>
              ))}
            </ul>
          )}
        </EApprovalSectionCard>
      </div>

      {(data.top_forms.length > 0 || data.submissions_over_time.length > 0) ? (
        <p className="text-xs text-muted-foreground">
          Chart drill-downs open the submissions list with matching filters. Point charts that link to
          a specific day/form use the hrefs returned by the analytics API.
        </p>
      ) : null}
    </div>
  );
}
