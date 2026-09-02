"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Package, ShoppingBag, Handshake, FileText } from "lucide-react";

import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DatePicker } from "@/components/ui/date-picker";
import {
  fetchPurchaseMonitoringReport,
  type PurchaseMonitoringReport,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";

function peso(n: number): string {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    maximumFractionDigits: 2,
  }).format(n);
}

export function PurchaseMonitoringPageClient() {
  const [dateFrom, setDateFrom] = useState("2025-12-31");
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10));
  const [supplierId, setSupplierId] = useState("");
  const [transactionType, setTransactionType] = useState("all");
  const [report, setReport] = useState<PurchaseMonitoringReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load(next?: {
    date_from?: string;
    date_to?: string;
    supplier_id?: string;
    transaction_type?: string;
  }) {
    setLoading(true);
    try {
      const data = await fetchPurchaseMonitoringReport({
        date_from: next?.date_from ?? dateFrom,
        date_to: next?.date_to ?? dateTo,
        supplier_id: (next?.supplier_id ?? supplierId) || undefined,
        transaction_type:
          (next?.transaction_type ?? transactionType) === "all"
            ? undefined
            : (next?.transaction_type ?? transactionType),
      });
      setReport(data);
      setError(null);
    } catch {
      setError("Unable to load Purchase Monitoring.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- initial load only
  }, []);

  const supplierChart = useMemo(
    () =>
      (report?.by_supplier ?? []).map((row) => ({
        key: row.supplier_id,
        label: row.supplier_name,
        value: row.total,
      })),
    [report],
  );

  const monthly = report?.monthly_trend ?? [];

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm text-muted-foreground">
              <Link href="/dynamic-entities" className="underline-offset-4 hover:underline">
                Entities
              </Link>
              {" / Operations"}
            </p>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">Purchase Monitoring Report</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Track purchase orders, goods receipts, and supplier spend from dynamic ATC records.
            </p>
          </div>
          <Button variant="outline" size="sm" render={<Link href="/dynamic-entities/purchase_transactions" />}>
            Open transactions
          </Button>
        </header>

        <form
          className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Start date</span>
            <DatePicker className="h-9 w-40" value={dateFrom} onChange={setDateFrom} />
          </label>
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">End date</span>
            <DatePicker className="h-9 w-40" value={dateTo} onChange={setDateTo} />
          </label>
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Supplier</span>
            <select
              className="flex h-9 min-w-[200px] rounded-md border border-input bg-background px-3 text-sm"
              value={supplierId}
              onChange={(e) => setSupplierId(e.target.value)}
            >
              <option value="">All suppliers</option>
              {(report?.filter_options.suppliers ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Type</span>
            <select
              className="flex h-9 min-w-[160px] rounded-md border border-input bg-background px-3 text-sm"
              value={transactionType}
              onChange={(e) => setTransactionType(e.target.value)}
            >
              <option value="all">All types</option>
              {(report?.filter_options.transaction_types ?? []).map((t) => (
                <option key={t} value={t}>
                  {t}
                </option>
              ))}
            </select>
          </label>
          <Button type="submit" size="sm" disabled={loading}>
            Update
          </Button>
        </form>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {report?.message ? <p className="text-sm text-amber-700 dark:text-amber-400">{report.message}</p> : null}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard
            title="Total purchases"
            value={peso(report?.kpis.total_purchases ?? 0)}
            sub={`${report?.kpis.total_transactions ?? 0} Transactions`}
            icon={<ShoppingBag className="h-4 w-4" />}
          />
          <KpiCard
            title="Purchase orders"
            value={peso(report?.kpis.purchase_orders_total ?? 0)}
            sub={`${report?.kpis.purchase_orders_count ?? 0} POs`}
            icon={<FileText className="h-4 w-4" />}
          />
          <KpiCard
            title="Goods received"
            value={peso(report?.kpis.goods_received_total ?? 0)}
            sub={`${report?.kpis.goods_received_count ?? 0} Receipts`}
            icon={<Package className="h-4 w-4" />}
          />
          <KpiCard
            title="Active suppliers"
            value={String(report?.kpis.active_suppliers ?? 0)}
            sub="With transactions"
            icon={<Handshake className="h-4 w-4" />}
          />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <Card className="rounded-xl">
            <CardHeader>
              <CardTitle className="text-base font-medium">Monthly purchase trend</CardTitle>
            </CardHeader>
            <CardContent className="h-[260px]">
              {monthly.length === 0 ? (
                <p className="text-sm text-muted-foreground">No monthly data for the selected filters.</p>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={monthly}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                    <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                    <YAxis tick={{ fontSize: 11 }} />
                    <Tooltip formatter={(v) => peso(Number(v ?? 0))} />
                    <Legend />
                    <Bar dataKey="purchase_orders" name="Purchase Orders" fill="#2563EB" radius={4} />
                    <Bar dataKey="goods_receipts" name="Goods Receipts" fill="#16A34A" radius={4} />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          <DashboardDonutChart
            title="Purchases by supplier"
            data={supplierChart}
            valueLabel="Spend"
            emptyMessage="No supplier spend for the selected filters."
            height={260}
          />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <Card className="rounded-xl">
            <CardHeader>
              <CardTitle className="text-base font-medium">
                Recent purchase transactions
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                  {report?.recent_transactions.length ?? 0} records
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="min-w-full text-[13px]">
                <thead className="text-left text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2">Ref #</th>
                    <th className="px-2 py-2">Date</th>
                    <th className="px-2 py-2">Supplier</th>
                    <th className="px-2 py-2">Type</th>
                    <th className="px-2 py-2">Status</th>
                    <th className="px-2 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {(report?.recent_transactions ?? []).map((row) => (
                    <tr key={row.id} className="border-t border-border/70">
                      <td className="px-2 py-2">
                        <Link
                          href={`/dynamic-entities/records/${row.id}`}
                          className="font-medium underline-offset-4 hover:underline"
                        >
                          {row.ref || row.id.slice(0, 8)}
                        </Link>
                      </td>
                      <td className="px-2 py-2 text-muted-foreground">{row.date || "—"}</td>
                      <td className="px-2 py-2">{row.supplier}</td>
                      <td className="px-2 py-2">
                        <span className="rounded bg-slate-800 px-1.5 py-0.5 text-[11px] text-white dark:bg-slate-200 dark:text-slate-900">
                          {row.type || "—"}
                        </span>
                      </td>
                      <td className="px-2 py-2">
                        <span className="rounded bg-slate-800 px-1.5 py-0.5 text-[11px] text-white dark:bg-slate-200 dark:text-slate-900">
                          {row.status || "—"}
                        </span>
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">{peso(row.total_amount)}</td>
                    </tr>
                  ))}
                  {(report?.recent_transactions.length ?? 0) === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-2 py-6 text-center text-muted-foreground">
                        {loading ? "Loading…" : "No transactions found."}
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </CardContent>
          </Card>

          <Card className="rounded-xl">
            <CardHeader>
              <CardTitle className="text-base font-medium">Top purchased items</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="min-w-full text-[13px]">
                <thead className="text-left text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2">Description</th>
                    <th className="px-2 py-2 text-right">Qty</th>
                    <th className="px-2 py-2 text-right">Total cost</th>
                  </tr>
                </thead>
                <tbody>
                  {(report?.top_items ?? []).map((row, i) => (
                    <tr key={`${row.description}-${i}`} className="border-t border-border/70">
                      <td className="px-2 py-2">{row.description}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{row.qty.toFixed(2)}</td>
                      <td className="px-2 py-2 text-right tabular-nums">{peso(row.total_cost)}</td>
                    </tr>
                  ))}
                  {(report?.top_items.length ?? 0) === 0 ? (
                    <tr>
                      <td colSpan={3} className="px-2 py-6 text-center text-muted-foreground">
                        No line items imported yet.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </div>
      </div>
    </PermissionGate>
  );
}

function KpiCard({
  title,
  value,
  sub,
  icon,
}: {
  title: string;
  value: string;
  sub: string;
  icon: React.ReactNode;
}) {
  return (
    <Card className="rounded-xl">
      <CardContent className="flex items-start justify-between gap-3 p-4">
        <div>
          <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{title}</div>
          <div className="mt-1 text-xl font-semibold tabular-nums text-foreground">{value}</div>
          <div className="mt-0.5 text-xs text-muted-foreground">{sub}</div>
        </div>
        <div className="rounded-lg bg-sky-500/10 p-2 text-sky-700 dark:text-sky-400">{icon}</div>
      </CardContent>
    </Card>
  );
}
