"use client";

import Link from "next/link";
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
  date: string;
  kpis: {
    total_gross_sales: number;
    sales_invoices: number;
    invoice_count: number;
    vat_collected: number;
    sales_returns: number;
  };
  by_type: Array<{ key: string; label: string; value: number }>;
  top_customers: Array<{ key: string; label: string; value: number }>;
  transactions: Array<{
    id: string;
    transaction_no: string;
    customer: string;
    type: string;
    status: string;
    subtotal: number;
    tax: number;
    discount: number;
    total_amount: number;
  }>;
};

export function DailySalesReportPageClient() {
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    try {
      const data = await fetchDynFinanceReport<Report>("daily-sales", { date });
      setReport(data);
      setError(null);
    } catch {
      setError("Unable to load Daily Sales report.");
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
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm text-muted-foreground">Operations</p>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">Daily Sales Report</h1>
            <p className="mt-1 text-sm text-muted-foreground">Daily transaction summary & analytics.</p>
          </div>
        </header>

        <form
          className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Select date</span>
            <DatePicker className="h-9 w-44" value={date} onChange={setDate} />
          </label>
          <Button type="submit" size="sm" disabled={loading}>Update</Button>
        </form>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Kpi title="Total gross sales" value={formatPeso(k?.total_gross_sales ?? 0)} badge="Posted" />
          <Kpi title="Sales invoices" value={formatPeso(k?.sales_invoices ?? 0)} badge={`${k?.invoice_count ?? 0} Bills`} />
          <Kpi title="VAT collected" value={formatPeso(k?.vat_collected ?? 0)} badge="12% VAT" />
          <Kpi title="Sales returns" value={formatPeso(k?.sales_returns ?? 0)} badge="Returns" />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardDonutChart title="Sales by transaction type" data={report?.by_type ?? []} valueLabel="Sales" height={240} />
          <DashboardBarChart title="Top customers today" data={report?.top_customers ?? []} valueLabel="Sales" height={240} />
        </div>

        <Card className="rounded-xl">
          <CardHeader>
            <CardTitle className="text-base font-medium">
              Transaction details
              <span className="ml-2 text-xs font-normal text-muted-foreground">
                {report?.transactions.length ?? 0} transactions
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            {(report?.transactions.length ?? 0) === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No sales transactions found for {report?.date ?? date}.
              </p>
            ) : (
              <table className="min-w-full text-[13px]">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2">Transaction no.</th>
                    <th className="px-2 py-2">Customer</th>
                    <th className="px-2 py-2">Type</th>
                    <th className="px-2 py-2">Status</th>
                    <th className="px-2 py-2 text-right">Subtotal</th>
                    <th className="px-2 py-2 text-right">Tax</th>
                    <th className="px-2 py-2 text-right">Discount</th>
                    <th className="px-2 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {(report?.transactions ?? []).map((row) => (
                    <tr key={row.id} className="border-t border-border/70">
                      <td className="px-2 py-2">
                        <Link href={`/dynamic-entities/records/${row.id}`} className="font-medium underline-offset-4 hover:underline">
                          {row.transaction_no || row.id.slice(0, 8)}
                        </Link>
                      </td>
                      <td className="px-2 py-2">{row.customer}</td>
                      <td className="px-2 py-2">{row.type}</td>
                      <td className="px-2 py-2">{row.status}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.subtotal)}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.tax)}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.discount)}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.total_amount)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>
      </div>
    </PermissionGate>
  );
}

function Kpi({ title, value, badge }: { title: string; value: string; badge?: string }) {
  return (
    <Card className="rounded-xl">
      <CardContent className="p-4">
        <div className="flex items-center justify-between gap-2">
          <div className="text-xs font-medium uppercase text-muted-foreground">{title}</div>
          {badge ? (
            <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
              {badge}
            </span>
          ) : null}
        </div>
        <div className="mt-1 text-xl font-semibold tabular-nums">{value}</div>
      </CardContent>
    </Card>
  );
}
