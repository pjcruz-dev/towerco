"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { AlertTriangle, ArrowRight, CheckCircle2, Info } from "lucide-react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  fetchAtcExecutiveDashboard,
  type AtcExecutiveDashboard,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { formatPeso } from "../reports/format-peso";

export function AtcExecutiveDashboardPageClient() {
  const [data, setData] = useState<AtcExecutiveDashboard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetchAtcExecutiveDashboard()
      .then((row) => {
        if (!cancelled) {
          setData(row);
          setError(null);
        }
      })
      .catch(() => {
        if (!cancelled) setError("Unable to load executive dashboard.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const k = data?.kpis;

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-semibold tracking-tight">Executive Dashboard</h1>
              <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                LIVE
              </span>
            </div>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Portfolio, RFTI pipeline, construction, procurement, and capital KPIs from dynamic entity records.
            </p>
            {data?.generated_at ? (
              <p className="mt-1 text-xs text-muted-foreground">
                Updated {new Date(data.generated_at).toLocaleString()}
              </p>
            ) : null}
          </div>
          <Button variant="outline" size="sm" render={<Link href="/dynamic-entities/tower_sites" />}>
            Open tower sites
          </Button>
        </header>

        {loading ? <p className="text-sm text-muted-foreground">Loading dashboard…</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {data?.message ? <p className="text-sm text-amber-700 dark:text-amber-400">{data.message}</p> : null}

        {!loading && data ? (
          <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <Kpi title="Sites" value={String(k?.sites_total ?? 0)} sub={`${k?.sites_wip ?? 0} WIP`} />
              <Kpi title="RFTI / RFI tagged" value={String(k?.sites_rfti_ready ?? 0)} />
              <Kpi
                title="Construction"
                value={String(k?.construction_active ?? 0)}
                sub={`${k?.construction_completed ?? 0} completed`}
              />
              <Kpi
                title="Purchases MTD"
                value={formatPeso(k?.purchases_mtd ?? 0)}
                sub={`${k?.open_procurement_requests ?? 0} open PRs`}
              />
              <Kpi
                title="Capex booked"
                value={formatPeso(k?.capex_booked ?? 0)}
                sub={`Opex YTD ${formatPeso(k?.opex_ytd ?? 0)}`}
              />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
              <Card className="rounded-xl lg:col-span-1">
                <CardHeader>
                  <CardTitle className="text-base font-medium">Needs attention</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {(data.attention ?? []).map((item, index) => (
                    <Link
                      key={`${item.href}:${item.tone}:${item.headline}:${index}`}
                      href={item.href}
                      className="flex gap-3 rounded-lg border border-border/80 p-3 transition-colors hover:bg-muted/40"
                    >
                      <ToneIcon tone={item.tone} />
                      <div className="min-w-0">
                        <div className="text-sm font-medium text-foreground">{item.headline}</div>
                        <div className="mt-0.5 text-xs text-muted-foreground">{item.detail}</div>
                      </div>
                    </Link>
                  ))}
                </CardContent>
              </Card>

              <div className="grid gap-4 lg:col-span-2">
                <DashboardBarChart
                  title="Site portfolio by region"
                  data={data.portfolio.by_region}
                  valueLabel="Sites"
                  height={240}
                />
              </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              <DashboardDonutChart
                title="Project type mix"
                data={data.portfolio.by_project_type}
                valueLabel="Sites"
                height={260}
              />
              <DashboardBarChart
                title="RFTI pipeline aging"
                data={data.rfti_pipeline.aging}
                valueLabel="Sites"
                height={260}
              />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              <DashboardBarChart
                title="Sites by milestone"
                data={data.rfti_pipeline.by_milestone}
                valueLabel="Sites"
                layout="horizontal"
                height={280}
              />
              <DashboardDonutChart
                title="Construction status"
                data={data.construction.by_status}
                valueLabel="Projects"
                height={280}
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Kpi title="Construction budget" value={formatPeso(data.construction.total_budget)} />
              <Kpi title="Construction actual" value={formatPeso(data.construction.total_actual)} />
              <Kpi
                title="Sales MTD"
                value={formatPeso(data.finance.sales_mtd)}
                sub={`${data.finance.sales_count_mtd} invoices`}
              />
              <Kpi
                title="Active suppliers (MTD)"
                value={String(data.procurement.suppliers_active)}
                sub={`${data.procurement.purchase_count_mtd} receipts`}
              />
            </div>

            <Card className="rounded-xl">
              <CardHeader>
                <CardTitle className="text-base font-medium">Quick links</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-2">
                {(data.quick_links ?? []).map((link) => (
                  <Button key={link.href} variant="outline" size="sm" render={<Link href={link.href} />}>
                    {link.label}
                    <ArrowRight className="ml-1 h-3.5 w-3.5" />
                  </Button>
                ))}
              </CardContent>
            </Card>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}

function Kpi({ title, value, sub }: { title: string; value: string; sub?: string }) {
  return (
    <Card className="rounded-xl">
      <CardContent className="p-4">
        <div className="text-xs font-medium text-muted-foreground">{title}</div>
        <div className="mt-1 text-xl font-semibold tabular-nums text-foreground">{value}</div>
        {sub ? <div className="mt-0.5 text-xs text-muted-foreground">{sub}</div> : null}
      </CardContent>
    </Card>
  );
}

function ToneIcon({ tone }: { tone: string }) {
  if (tone === "danger") {
    return <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />;
  }
  if (tone === "warning") {
    return <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />;
  }
  if (tone === "success") {
    return <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />;
  }
  return <Info className="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400" />;
}
