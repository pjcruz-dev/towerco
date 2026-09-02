"use client";

import { useEffect, useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DatePicker } from "@/components/ui/date-picker";
import { fetchDynFinanceReport } from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { formatPeso } from "../format-peso";

type Report = {
  message: string | null;
  kpis: {
    towers_supplied: number;
    issued: number;
    returned: number;
    net_material_cost: number;
    average_per_tower: number;
    item_lines: number;
  };
  by_region: Array<{ key: string; label: string; value: number }>;
  by_category: Array<{ key: string; label: string; value: number }>;
  top_items: Array<{ item: string; qty: number; towers: number; value: number }>;
  by_warehouse: Array<{ warehouse: string; slips: number; towers: number; value: number }>;
  filter_options: { regions: string[] };
};

export function MaterialsIssuedReportPageClient() {
  const [dateFrom, setDateFrom] = useState("2026-03-01");
  const [dateTo, setDateTo] = useState("2026-08-31");
  const [region, setRegion] = useState("");
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    try {
      const data = await fetchDynFinanceReport<Report>("materials-issued", {
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        region: region || undefined,
      });
      setReport(data);
      setError(null);
    } catch {
      setError("Unable to load Materials Issued report.");
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
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">Materials Issued per Tower</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            What each tower consumed from warehouse stock, valued at cost, net of returns.
          </p>
        </header>

        <form
          className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <DatePicker className="h-9 w-40" value={dateFrom} onChange={setDateFrom} />
          <DatePicker className="h-9 w-40" value={dateTo} onChange={setDateTo} />
          <select className="h-9 rounded-md border border-input bg-background px-3 text-sm" value={region} onChange={(e) => setRegion(e.target.value)}>
            <option value="">All regions</option>
            {(report?.filter_options.regions ?? []).map((r) => (
              <option key={r} value={r}>{r}</option>
            ))}
          </select>
          <Button type="submit" size="sm" disabled={loading}>Update</Button>
        </form>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <Kpi title="Towers supplied" value={String(k?.towers_supplied ?? 0)} />
          <Kpi title="Issued" value={formatPeso(k?.issued ?? 0)} />
          <Kpi title="Returned" value={formatPeso(k?.returned ?? 0)} />
          <Kpi title="Net material cost" value={formatPeso(k?.net_material_cost ?? 0)} />
          <Kpi title="Average per tower" value={formatPeso(k?.average_per_tower ?? 0)} />
          <Kpi title="Item lines" value={String(k?.item_lines ?? 0)} />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardBarChart title="Materials issued by region" data={report?.by_region ?? []} valueLabel="Net issued" height={260} />
          <DashboardDonutChart title="Issued value by cost category" data={report?.by_category ?? []} valueLabel="Value" height={260} />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <Card className="rounded-xl">
            <CardHeader><CardTitle className="text-base font-medium">Most consumed items</CardTitle></CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="min-w-full text-[13px]">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2">Item</th>
                    <th className="px-2 py-2 text-right">Qty</th>
                    <th className="px-2 py-2 text-right">Towers</th>
                    <th className="px-2 py-2 text-right">Value</th>
                  </tr>
                </thead>
                <tbody>
                  {(report?.top_items ?? []).map((row) => (
                    <tr key={row.item} className="border-t border-border/70">
                      <td className="px-2 py-2">{row.item}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{row.qty.toFixed(2)}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{row.towers}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
          <Card className="rounded-xl">
            <CardHeader><CardTitle className="text-base font-medium">Issued by warehouse</CardTitle></CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="min-w-full text-[13px]">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2">Warehouse</th>
                    <th className="px-2 py-2 text-right">Slips</th>
                    <th className="px-2 py-2 text-right">Towers</th>
                    <th className="px-2 py-2 text-right">Value</th>
                  </tr>
                </thead>
                <tbody>
                  {(report?.by_warehouse ?? []).map((row) => (
                    <tr key={row.warehouse} className="border-t border-border/70">
                      <td className="px-2 py-2">{row.warehouse}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{row.slips}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{row.towers}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </div>
      </div>
    </PermissionGate>
  );
}

function Kpi({ title, value }: { title: string; value: string }) {
  return (
    <Card className="rounded-xl">
      <CardContent className="p-4">
        <div className="text-xs font-medium uppercase text-muted-foreground">{title}</div>
        <div className="mt-1 text-xl font-semibold tabular-nums">{value}</div>
      </CardContent>
    </Card>
  );
}
