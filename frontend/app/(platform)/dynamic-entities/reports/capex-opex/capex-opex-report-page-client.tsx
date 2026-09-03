"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { fetchDynFinanceReport } from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { formatPeso } from "../format-peso";

type Report = {
  message: string | null;
  kpis: Record<string, number>;
  avg_capex_by_structure: Array<{ key: string; label: string; value: number }>;
  monthly_opex_mix: Array<{ key: string; label: string; value: number }>;
  averages_by_region: Array<{
    region: string;
    sites: number;
    total_capex: number;
    avg_capex: number;
    total_opex: number;
    avg_opex: number;
  }>;
  definitions: { capex: string[]; opex: string[] };
  filter_options: { regions: string[]; structure_types: string[] };
};

export function CapexOpexReportPageClient() {
  const [region, setRegion] = useState("");
  const [structure, setStructure] = useState("");
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    try {
      const data = await fetchDynFinanceReport<Report>("capex-opex", {
        region: region || undefined,
        structure_type: structure || undefined,
      });
      setReport(data);
      setError(null);
    } catch {
      setError("Unable to load Capex & Opex report.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const k = report?.kpis;

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header>
          <p className="text-sm text-muted-foreground">Cost & Capital</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">Capex & Opex per Site</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Capital spend from fixed assets / Capex GL, operating costs from site operating cost records.
          </p>
        </header>

        <form
          className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <Select
            className="h-9 w-44"
            value={region}
            onChange={(e) => setRegion(e.target.value)}
          >
            <option value="">All regions</option>
            {(report?.filter_options.regions ?? []).map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </Select>
          <Select
            className="h-9 w-44"
            value={structure}
            onChange={(e) => setStructure(e.target.value)}
          >
            <option value="">All structure types</option>
            {(report?.filter_options.structure_types ?? []).map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </Select>
          <Button type="submit" size="sm" disabled={loading}>
            Update
          </Button>
        </form>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {report?.message ? <p className="text-sm text-amber-700 dark:text-amber-400">{report.message}</p> : null}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <Kpi title="Sites in scope" value={String(k?.sites_in_scope ?? 0)} />
          <Kpi title="Total Capex" value={formatPeso(k?.total_capex ?? 0)} sub={`${k?.capex_sites ?? 0} sites costed`} />
          <Kpi title="Avg Capex / site" value={formatPeso(k?.avg_capex ?? 0)} />
          <Kpi title="Total Opex" value={formatPeso(k?.total_opex ?? 0)} sub={`${k?.opex_sites ?? 0} sites costed`} />
          <Kpi title="Avg Opex / site" value={formatPeso(k?.avg_opex ?? 0)} />
          <Kpi
            title="Avg Opex / site / mo"
            value={formatPeso(k?.avg_opex_per_month ?? 0)}
            sub={`${k?.opex_months ?? 0} months of data`}
          />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardBarChart
            title="Average capex per site, by structure type"
            data={report?.avg_capex_by_structure ?? []}
            valueLabel="Avg Capex"
            layout="horizontal"
            height={280}
          />
          <DashboardDonutChart
            title="Monthly opex mix"
            data={report?.monthly_opex_mix ?? []}
            valueLabel="Opex"
            height={280}
          />
        </div>

        <Card className="rounded-xl">
          <CardHeader>
            <CardTitle className="text-base font-medium">Averages by region</CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="min-w-full text-[13px]">
              <thead className="text-left text-xs text-muted-foreground">
                <tr>
                  <th className="px-2 py-2">Region</th>
                  <th className="px-2 py-2">Sites</th>
                  <th className="px-2 py-2 text-right">Total Capex</th>
                  <th className="px-2 py-2 text-right">Avg Capex</th>
                  <th className="px-2 py-2 text-right">Total Opex</th>
                  <th className="px-2 py-2 text-right">Avg Opex</th>
                </tr>
              </thead>
              <tbody>
                {(report?.averages_by_region ?? []).map((row) => (
                  <tr key={row.region} className="border-t border-border/70">
                    <td className="px-2 py-2 font-medium">{row.region}</td>
                    <td className="px-2 py-2">{row.sites}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.total_capex)}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.avg_capex)}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.total_opex)}</td>
                    <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.avg_opex)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>

        <Card className="rounded-xl">
          <CardHeader>
            <CardTitle className="text-base font-medium">What qualifies as Capex and Opex</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2 text-sm">
            <div>
              <div className="font-medium text-sky-700 dark:text-sky-400">Capex</div>
              <ul className="mt-1 list-disc pl-5 text-muted-foreground">
                {(report?.definitions.capex ?? []).map((x) => (
                  <li key={x}>{x}</li>
                ))}
              </ul>
            </div>
            <div>
              <div className="font-medium text-amber-700 dark:text-amber-400">Opex</div>
              <ul className="mt-1 list-disc pl-5 text-muted-foreground">
                {(report?.definitions.opex ?? []).map((x) => (
                  <li key={x}>{x}</li>
                ))}
              </ul>
            </div>
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
