"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchDynFinanceReport } from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { formatPeso } from "../format-peso";

type Report = {
  message: string | null;
  kpis: {
    towers: number;
    budgeted: number;
    committed: number;
    actual: number;
    actual_pct_of_budget: number;
    variance: number;
    under_budget: boolean;
    opex_to_date: number;
  };
  by_region: Array<{ region: string; budget: number; committed: number; actual: number }>;
  by_category: Array<{ key: string; label: string; value: number }>;
  register: Array<{
    site_id: string;
    site_code: string;
    site_name: string;
    region: string;
    type: string;
    budget: number;
    committed: number;
    actual: number;
    variance: number;
    utilisation: number;
  }>;
  filter_options: { regions: string[]; project_types: string[] };
};

export function CostVsBudgetReportPageClient() {
  const [region, setRegion] = useState("");
  const [projectType, setProjectType] = useState("");
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    try {
      const data = await fetchDynFinanceReport<Report>("cost-vs-budget", {
        region: region || undefined,
        project_type: projectType || undefined,
      });
      setReport(data);
      setError(null);
    } catch {
      setError("Unable to load Cost vs Budget report.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const k = report?.kpis;
  const regionBudget = (report?.by_region ?? []).map((r) => ({
    key: `${r.region}-budget`,
    label: r.region,
    value: r.budget,
  }));

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header>
          <p className="text-sm text-muted-foreground">Cost & Capital</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">Cost vs Budget per Tower</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Budget and actual from construction projects / fixed assets; committed from purchases linked to sites.
          </p>
        </header>

        <form
          className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <select className="h-9 rounded-md border border-input bg-background px-3 text-sm" value={region} onChange={(e) => setRegion(e.target.value)}>
            <option value="">All regions</option>
            {(report?.filter_options.regions ?? []).map((r) => (
              <option key={r} value={r}>{r}</option>
            ))}
          </select>
          <select className="h-9 rounded-md border border-input bg-background px-3 text-sm" value={projectType} onChange={(e) => setProjectType(e.target.value)}>
            <option value="">All project types</option>
            {(report?.filter_options.project_types ?? []).map((r) => (
              <option key={r} value={r}>{r}</option>
            ))}
          </select>
          <Button type="submit" size="sm" disabled={loading}>Update</Button>
        </form>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {report?.message ? <p className="text-sm text-amber-700 dark:text-amber-400">{report.message}</p> : null}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <Kpi title="Towers" value={String(k?.towers ?? 0)} />
          <Kpi title="Budgeted" value={formatPeso(k?.budgeted ?? 0)} />
          <Kpi title="Committed (PO)" value={formatPeso(k?.committed ?? 0)} />
          <Kpi title="Actual" value={formatPeso(k?.actual ?? 0)} sub={`${k?.actual_pct_of_budget ?? 0}% of budget`} />
          <Kpi
            title="Variance"
            value={formatPeso(k?.variance ?? 0)}
            sub={k?.under_budget ? "Under budget" : "Over budget"}
          />
          <Kpi title="Opex to date" value={formatPeso(k?.opex_to_date ?? 0)} />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardBarChart title="Budget by region" data={regionBudget} valueLabel="Budget" height={260} />
          <DashboardDonutChart title="Actual spend by cost category" data={report?.by_category ?? []} valueLabel="Spend" height={260} />
        </div>

        <Card className="rounded-xl">
          <CardHeader>
            <CardTitle className="text-base font-medium">Per-Tower Cost Register</CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="min-w-full text-[13px]">
              <thead className="text-left text-xs text-muted-foreground">
                <tr>
                  <th className="px-2 py-2">Site code</th>
                  <th className="px-2 py-2">Site name</th>
                  <th className="px-2 py-2">Region</th>
                  <th className="px-2 py-2">Type</th>
                  <th className="px-2 py-2 text-right">Budget</th>
                  <th className="px-2 py-2 text-right">Committed</th>
                  <th className="px-2 py-2 text-right">Actual</th>
                  <th className="px-2 py-2 text-right">Variance</th>
                  <th className="px-2 py-2">Utilisation</th>
                </tr>
              </thead>
              <tbody>
                {(report?.register ?? []).map((row) => (
                  <tr key={row.site_id} className="border-t border-border/70">
                    <td className="px-2 py-2">
                      <Link href={`/dynamic-entities/records/${row.site_id}`} className="font-medium underline-offset-4 hover:underline">
                        {row.site_code || row.site_id.slice(0, 8)}
                      </Link>
                    </td>
                    <td className="px-2 py-2 max-w-[220px] truncate">{row.site_name}</td>
                    <td className="px-2 py-2">{row.region}</td>
                    <td className="px-2 py-2">{row.type}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.budget)}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.committed)}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.actual)}</td>
                    <td className={`px-2 py-2 text-right tabular-nums ${row.variance < 0 ? "text-red-600 dark:text-red-400" : ""}`}>
                      {formatPeso(row.variance)}
                    </td>
                    <td className="px-2 py-2 min-w-[120px]">
                      <div className="flex items-center gap-2">
                        <div className="h-1.5 flex-1 rounded-full bg-muted">
                          <div
                            className={`h-1.5 rounded-full ${row.utilisation > 100 ? "bg-red-500" : "bg-sky-500"}`}
                            style={{ width: `${Math.min(100, Math.max(0, row.utilisation))}%` }}
                          />
                        </div>
                        <span className="text-xs tabular-nums text-muted-foreground">{row.utilisation}%</span>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </div>
    </PermissionGate>
  );
}

function Kpi({ title, value, sub }: { title: string; value: string; sub?: string }) {
  return (
    <Card className="rounded-xl">
      <CardContent className="p-4">
        <div className="text-xs font-medium uppercase text-muted-foreground">{title}</div>
        <div className="mt-1 text-xl font-semibold tabular-nums">{value}</div>
        {sub ? <div className="mt-0.5 text-xs text-muted-foreground">{sub}</div> : null}
      </CardContent>
    </Card>
  );
}
