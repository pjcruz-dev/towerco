"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DatePicker } from "@/components/ui/date-picker";
import {
  fetchDynExtendedReport,
  type DynExtendedReport,
  type DynExtendedReportKey,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { formatPeso } from "./format-peso";

const REPORT_META: Record<
  DynExtendedReportKey,
  { eyebrow: string; title: string; description: string }
> = {
  "cas-executive-dashboard": {
    eyebrow: "Finance",
    title: "CAS Executive Dashboard",
    description: "Cash, AR/AP, sales and purchase KPIs for commercial and accounting leadership.",
  },
  "ar-ap-aging": {
    eyebrow: "Finance",
    title: "AR & AP Aging",
    description: "Open receivables and payables bucketed by days past due.",
  },
  "stock-on-hand": {
    eyebrow: "Procurement",
    title: "Stock on Hand",
    description: "Net inventory by warehouse and product from stock movements.",
  },
  "petty-cash": {
    eyebrow: "Finance",
    title: "Petty Cash",
    description: "Cash disbursements and petty cash vouchers from payments & receipts.",
  },
  "live-tower-build-forecasts": {
    eyebrow: "Tower Operations",
    title: "LIVE Tower Build & Forecasts",
    description: "Construction progress and planned finish forecasts by project.",
  },
  "site-portfolio-status": {
    eyebrow: "Tower Operations",
    title: "Site Portfolio Status",
    description: "Portfolio mix by region, status, and project type.",
  },
  "rfti-pipeline": {
    eyebrow: "Tower Operations",
    title: "RFTI Pipeline & SLA",
    description: "Days-to-RFTI aging and milestone distribution across tower sites.",
  },
  "saq-milestone-aging": {
    eyebrow: "Tower Operations",
    title: "SAQ Milestone Aging",
    description: "SAQ tracker status mix and age since last update.",
  },
  "permit-status-compliance": {
    eyebrow: "Tower Operations",
    title: "Permit Status & Compliance",
    description: "Site permit approvals, open statuses, and expiry visibility.",
  },
  "energization-power-status": {
    eyebrow: "Tower Operations",
    title: "Energization & Power Status",
    description: "Power tracker energization status across the portfolio.",
  },
  "colocation-tenancy": {
    eyebrow: "Tower Operations",
    title: "Colocation & Tenancy",
    description: "Colocation and telco tenancy records with rent and status.",
  },
  "trial-balance": {
    eyebrow: "Financial Statements",
    title: "Trial Balance",
    description: "Debit and credit balances by chart of accounts from the general ledger.",
  },
  "income-statement": {
    eyebrow: "Financial Statements",
    title: "Income Statement",
    description: "Revenue and expense summary from CoA classifications or operational aggregates.",
  },
  "balance-sheet": {
    eyebrow: "Financial Statements",
    title: "Balance Sheet",
    description: "Assets, liabilities, and equity snapshot.",
  },
  "cash-flow": {
    eyebrow: "Financial Statements",
    title: "Cash Flow",
    description: "Operating inflows and outflows from payments & receipts.",
  },
  "monthly-management-accounts": {
    eyebrow: "Financial Statements",
    title: "Monthly Management Accounts",
    description: "Month-to-date sales, purchases, opex, and contribution view.",
  },
  "bir-compliance": {
    eyebrow: "Financial Statements",
    title: "BIR Compliance",
    description: "BIR Form 2307 certificates and tax withheld totals.",
  },
};

function formatCell(value: unknown, format?: string): string {
  if (value === null || value === undefined || value === "") return "—";
  if (format === "money" && typeof value === "number") return formatPeso(value);
  if (format === "number" && typeof value === "number") {
    return new Intl.NumberFormat("en-PH", { maximumFractionDigits: 2 }).format(value);
  }
  return String(value);
}

export function DynExtendedReportPageClient({ report }: { report: DynExtendedReportKey }) {
  const meta = REPORT_META[report];
  const [data, setData] = useState<DynExtendedReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [warehouseId, setWarehouseId] = useState("");
  const [asOf, setAsOf] = useState("");
  const [month, setMonth] = useState("");

  const params = useMemo(() => {
    const next: Record<string, string | undefined> = {};
    if (report === "stock-on-hand" && warehouseId) next.warehouse_id = warehouseId;
    if (report === "ar-ap-aging" && asOf) next.as_of = asOf;
    if (report === "monthly-management-accounts" && month) next.month = month;
    return next;
  }, [report, warehouseId, asOf, month]);

  async function load() {
    setLoading(true);
    try {
      const row = await fetchDynExtendedReport(report, params);
      setData(row);
      setError(null);
    } catch {
      setError(`Unable to load ${meta.title}.`);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [report]);

  const showFilters =
    report === "stock-on-hand" || report === "ar-ap-aging" || report === "monthly-management-accounts";

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header>
          <p className="text-sm text-muted-foreground">{meta.eyebrow}</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">{meta.title}</h1>
          <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{meta.description}</p>
          {data?.generated_at ? (
            <p className="mt-1 text-xs text-muted-foreground">
              Updated {new Date(data.generated_at).toLocaleString()}
            </p>
          ) : null}
        </header>

        {showFilters ? (
          <form
            className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
            onSubmit={(e) => {
              e.preventDefault();
              void load();
            }}
          >
            {report === "stock-on-hand" ? (
              <select
                className="h-9 min-w-[220px] rounded-md border border-input bg-background px-3 text-sm"
                value={warehouseId}
                onChange={(e) => setWarehouseId(e.target.value)}
              >
                <option value="">All warehouses</option>
                {((data?.filter_options.warehouses as Array<{ id: string; name: string }> | undefined) ?? []).map(
                  (w) => (
                    <option key={w.id} value={w.id}>
                      {w.name}
                    </option>
                  ),
                )}
              </select>
            ) : null}
            {report === "ar-ap-aging" ? (
              <DatePicker className="h-9 w-40" value={asOf} onChange={setAsOf} />
            ) : null}
            {report === "monthly-management-accounts" ? (
              <input
                type="month"
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={month}
                onChange={(e) => setMonth(e.target.value)}
              />
            ) : null}
            <Button type="submit" size="sm" disabled={loading}>
              Update
            </Button>
          </form>
        ) : null}

        {loading ? <p className="text-sm text-muted-foreground">Loading report…</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {data?.message ? <p className="text-sm text-amber-700 dark:text-amber-400">{data.message}</p> : null}

        {!loading && data ? (
          <>
            {data.kpis.length > 0 ? (
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {data.kpis.map((kpi) => (
                  <Card key={kpi.key} className="rounded-xl">
                    <CardHeader className="pb-2">
                      <CardTitle className="text-sm font-medium text-muted-foreground">{kpi.label}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="text-2xl font-semibold tracking-tight">
                        {kpi.format === "money" ? formatPeso(Number(kpi.value)) : String(kpi.value)}
                      </div>
                      {kpi.sub ? <p className="mt-1 text-xs text-muted-foreground">{kpi.sub}</p> : null}
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : null}

            {data.charts.length > 0 ? (
              <div className="grid gap-4 lg:grid-cols-2">
                {data.charts.map((chart) =>
                  chart.type === "donut" ? (
                    <DashboardDonutChart
                      key={chart.id}
                      title={chart.title}
                      data={chart.data}
                      valueLabel={chart.valueLabel}
                      height={260}
                    />
                  ) : (
                    <DashboardBarChart
                      key={chart.id}
                      title={chart.title}
                      data={chart.data}
                      valueLabel={chart.valueLabel}
                      layout={chart.layout === "horizontal" ? "horizontal" : "vertical"}
                      height={260}
                    />
                  ),
                )}
              </div>
            ) : null}

            {data.tables.map((table) => (
              <Card key={table.id} className="rounded-xl">
                <CardHeader>
                  <CardTitle className="text-base font-medium">{table.title}</CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                  <table className="w-full min-w-[640px] text-left text-[13px]">
                    <thead className="border-b border-border text-muted-foreground">
                      <tr>
                        {table.columns.map((col) => (
                          <th key={col.key} className="px-2 py-2 font-medium">
                            {col.label}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {table.rows.length === 0 ? (
                        <tr>
                          <td className="px-2 py-6 text-muted-foreground" colSpan={table.columns.length}>
                            No rows for this report.
                          </td>
                        </tr>
                      ) : (
                        table.rows.map((row, index) => (
                          <tr key={index} className="border-b border-border/60">
                            {table.columns.map((col) => (
                              <td key={col.key} className="px-2 py-2 text-foreground">
                                {formatCell(row[col.key], col.format)}
                              </td>
                            ))}
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </CardContent>
              </Card>
            ))}

            {data.links.length > 0 ? (
              <Card className="rounded-xl">
                <CardHeader>
                  <CardTitle className="text-base font-medium">Related</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                  {data.links.map((link) => (
                    <Button key={link.href} variant="outline" size="sm" render={<Link href={link.href} />}>
                      {link.label}
                    </Button>
                  ))}
                </CardContent>
              </Card>
            ) : null}
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
