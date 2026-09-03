"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { FileDown, Printer, RefreshCw } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { getErrorMessage } from "@/lib/api/error";
import { apiClient } from "@/lib/api/client";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { formatPeso } from "../format-peso";

type InvoiceRow = {
  id: string;
  invoice_number: string;
  customer: string;
  customer_id: string | null;
  doc_type: string;
  invoice_date: string;
  due_date: string;
  original_amount: number;
  adjustments: number;
  balance_due: number;
  days: number;
  bracket: string;
  bracket_label: string;
  status: string;
  href: string;
  notes: string;
};

type Workbench = {
  message: string | null;
  generated_at: string;
  as_of: string;
  summary: {
    total: number;
    invoice_count: number;
    current: number;
    d31_60: number;
    d61_90: number;
    d91_120: number;
    over_120: number;
  };
  invoices: InvoiceRow[];
  filter_options: {
    customers: string[];
    brackets: Array<{ key: string; label: string }>;
  };
};

function bracketTone(bracket: string): string {
  if (bracket === "over_120") return "bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200";
  if (bracket === "91_120") return "bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-200";
  if (bracket === "61_90") return "bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200";
  if (bracket === "31_60") return "bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200";
  return "bg-muted text-muted-foreground";
}

function kpiTone(key: string): string {
  if (key === "31_60") return "border-sky-300 text-sky-700 dark:border-sky-700 dark:text-sky-300";
  if (key === "61_90") return "border-amber-300 text-amber-700 dark:border-amber-700 dark:text-amber-300";
  if (key === "91_120") return "border-orange-300 text-orange-700 dark:border-orange-700 dark:text-orange-300";
  if (key === "over_120") return "border-red-300 text-red-700 dark:border-red-700 dark:text-red-300";
  return "border-border";
}

export function InvoiceAgingWorkbenchPageClient() {
  const [data, setData] = useState<Workbench | null>(null);
  const [search, setSearch] = useState("");
  const [customer, setCustomer] = useState("");
  const [bracket, setBracket] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [adjAmount, setAdjAmount] = useState("");
  const [adjNote, setAdjNote] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiClient.get<{ data: Workbench }>(
        "/dynamic-entities/reports/aging-invoice-adjustments",
        {
          params: {
            search: search || undefined,
            customer: customer || undefined,
            bracket: bracket || undefined,
          },
        },
      );
      setData(response.data.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [search, customer, bracket]);

  useEffect(() => {
    void load();
  }, [load]);

  const selected = useMemo(
    () => data?.invoices.find((r) => r.id === selectedId) ?? null,
    [data, selectedId],
  );

  function exportCsv() {
    if (!data) return;
    const headers = [
      "Invoice #",
      "Customer",
      "Date",
      "Original",
      "Adjustments",
      "Balance",
      "Days",
      "Bracket",
    ];
    const lines = [headers.join(",")];
    for (const row of data.invoices) {
      lines.push(
        [
          row.invoice_number,
          row.customer,
          row.invoice_date,
          row.original_amount,
          row.adjustments,
          row.balance_due,
          row.days,
          row.bracket_label,
        ]
          .map((v) => `"${String(v).replace(/"/g, '""')}"`)
          .join(","),
      );
    }
    const blob = new Blob(["\ufeff" + lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "invoice-aging.csv";
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  async function postAdjustment() {
    if (!selected) return;
    const amount = Number(adjAmount);
    if (!Number.isFinite(amount) || amount <= 0) {
      setError("Enter a valid adjustment amount.");
      return;
    }
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await apiClient.post("/dynamic-entities/reports/aging-invoice-adjustments/adjust", {
        record_id: selected.id,
        amount,
        note: adjNote || undefined,
      });
      setAdjAmount("");
      setAdjNote("");
      setNotice(`Adjustment posted on ${selected.invoice_number}.`);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const summary = data?.summary;

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-4 px-4 py-6 sm:px-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-xs font-medium text-muted-foreground">Finance</p>
            <h1 className="text-2xl font-semibold tracking-tight">
              Invoice Aging & AR Adjustments Workbench
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Inspect aging buckets and post AR adjustments against open sales invoices.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" disabled={!data} onClick={exportCsv}>
              <FileDown className="size-4 text-emerald-600" />
              Export Aging CSV
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => window.print()}>
              <Printer className="size-4" />
              Print PDF
            </Button>
            <Button type="button" size="sm" disabled={loading || busy} onClick={() => void load()}>
              <RefreshCw className={cn("size-4", loading && "animate-spin")} />
              Refresh Data
            </Button>
          </div>
        </header>

        {error ? (
          <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            {error}
          </div>
        ) : null}
        {notice ? (
          <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
            {notice}
          </div>
        ) : null}
        {data?.message ? (
          <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            {data.message}
          </div>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
          <Kpi
            title="Total Receivables"
            value={formatPeso(summary?.total ?? 0)}
            sub={`${summary?.invoice_count ?? 0} invoices`}
            className="lg:col-span-1"
          />
          <Kpi
            title="Current (0-30 Days)"
            value={formatPeso(summary?.current ?? 0)}
            className={kpiTone("current")}
          />
          <Kpi title="31-60 Days" value={formatPeso(summary?.d31_60 ?? 0)} className={kpiTone("31_60")} />
          <Kpi title="61-90 Days" value={formatPeso(summary?.d61_90 ?? 0)} className={kpiTone("61_90")} />
          <Kpi
            title="91-120 Days"
            value={formatPeso(summary?.d91_120 ?? 0)}
            className={kpiTone("91_120")}
          />
          <Kpi
            title="> 120 Days Overdue"
            value={formatPeso(summary?.over_120 ?? 0)}
            className={kpiTone("over_120")}
          />
        </div>

        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
          <section className="rounded-xl border bg-card shadow-sm">
            <div className="flex flex-wrap gap-2 border-b p-3">
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search Invoice / Customer..."
                className="h-9 max-w-xs"
              />
              <Select
                className="h-9 w-48"
                value={customer}
                onChange={(e) => setCustomer(e.target.value)}
              >
                <option value="">All Customers</option>
                {(data?.filter_options.customers ?? []).map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </Select>
              <Select
                className="h-9 w-48"
                value={bracket}
                onChange={(e) => setBracket(e.target.value)}
              >
                <option value="">All Aging Brackets</option>
                {(data?.filter_options.brackets ?? []).map((b) => (
                  <option key={b.key} value={b.key}>
                    {b.label}
                  </option>
                ))}
              </Select>
            </div>

            <div className="max-h-[560px] overflow-auto">
              <table className="w-full text-[13px]">
                <thead className="sticky top-0 bg-muted/80 text-left text-xs font-medium text-muted-foreground backdrop-blur">
                  <tr>
                    <th className="px-3 py-2">Invoice #</th>
                    <th className="px-3 py-2">Customer</th>
                    <th className="px-3 py-2">Date</th>
                    <th className="px-3 py-2 text-right">Original</th>
                    <th className="px-3 py-2 text-right">Adjustments</th>
                    <th className="px-3 py-2 text-right">Balance Due</th>
                    <th className="px-3 py-2 text-right">Days</th>
                    <th className="px-3 py-2">Bracket</th>
                    <th className="px-3 py-2 text-right">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr>
                      <td colSpan={9} className="px-3 py-10 text-center text-muted-foreground">
                        Loading…
                      </td>
                    </tr>
                  ) : (data?.invoices.length ?? 0) === 0 ? (
                    <tr>
                      <td colSpan={9} className="px-3 py-10 text-center text-muted-foreground">
                        No matching invoices.
                      </td>
                    </tr>
                  ) : (
                    data!.invoices.map((row) => (
                      <tr
                        key={row.id}
                        className={cn(
                          "cursor-pointer border-t hover:bg-muted/40",
                          selectedId === row.id && "bg-sky-50/80 dark:bg-sky-950/30",
                        )}
                        onClick={() => setSelectedId(row.id)}
                      >
                        <td className="px-3 py-2">
                          <Link
                            href={row.href}
                            className="font-medium text-sky-700 hover:underline dark:text-sky-300"
                            onClick={(e) => e.stopPropagation()}
                          >
                            #{row.invoice_number}
                          </Link>
                        </td>
                        <td className="px-3 py-2">
                          <div className="font-medium">{row.customer}</div>
                          <div className="text-[11px] text-muted-foreground">{row.doc_type}</div>
                        </td>
                        <td className="px-3 py-2 tabular-nums text-muted-foreground">
                          {row.invoice_date}
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums">
                          {formatPeso(row.original_amount)}
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums">
                          {formatPeso(row.adjustments)}
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums font-medium">
                          {formatPeso(row.balance_due)}
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums">{row.days}</td>
                        <td className="px-3 py-2">
                          <span
                            className={cn(
                              "inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-medium",
                              bracketTone(row.bracket),
                            )}
                          >
                            {row.bracket_label}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-right">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7 text-sky-700"
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedId(row.id);
                            }}
                          >
                            + Adjust
                          </Button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </section>

          <aside className="rounded-xl border bg-card p-4 shadow-sm lg:sticky lg:top-4 lg:self-start">
            <h2 className="text-base font-medium">Invoice Detail & Adjustment</h2>
            {!selected ? (
              <p className="mt-6 text-sm text-muted-foreground">
                No Invoice Selected. Click any invoice row on the left to review details or post an
                adjustment.
              </p>
            ) : (
              <div className="mt-4 space-y-3 text-sm">
                <div>
                  <div className="text-xs text-muted-foreground">Invoice</div>
                  <Link href={selected.href} className="font-medium text-sky-700 hover:underline">
                    #{selected.invoice_number}
                  </Link>
                </div>
                <div>
                  <div className="text-xs text-muted-foreground">Customer</div>
                  <div className="font-medium">{selected.customer}</div>
                  <div className="text-xs text-muted-foreground">{selected.doc_type}</div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <div className="text-xs text-muted-foreground">Invoice date</div>
                    <div>{selected.invoice_date}</div>
                  </div>
                  <div>
                    <div className="text-xs text-muted-foreground">Due</div>
                    <div>{selected.due_date}</div>
                  </div>
                  <div>
                    <div className="text-xs text-muted-foreground">Balance</div>
                    <div className="font-medium">{formatPeso(selected.balance_due)}</div>
                  </div>
                  <div>
                    <div className="text-xs text-muted-foreground">Aging</div>
                    <div>
                      {selected.days}d · {selected.bracket_label}
                    </div>
                  </div>
                </div>
                {selected.notes ? (
                  <div>
                    <div className="text-xs text-muted-foreground">History</div>
                    <pre className="mt-1 max-h-28 overflow-auto whitespace-pre-wrap rounded-md bg-muted/40 p-2 text-[11px]">
                      {selected.notes}
                    </pre>
                  </div>
                ) : null}

                <div className="space-y-2 border-t pt-3">
                  <Label>Adjustment amount</Label>
                  <Input
                    type="number"
                    min={0.01}
                    step="0.01"
                    value={adjAmount}
                    onChange={(e) => setAdjAmount(e.target.value)}
                    placeholder="0.00"
                  />
                  <Label>Note</Label>
                  <Input
                    value={adjNote}
                    onChange={(e) => setAdjNote(e.target.value)}
                    placeholder="Reason / reference"
                  />
                  <Button
                    type="button"
                    className="w-full"
                    disabled={busy}
                    onClick={() => void postAdjustment()}
                  >
                    Post Adjustment
                  </Button>
                </div>
              </div>
            )}
          </aside>
        </div>
      </div>
    </PermissionGate>
  );
}

function Kpi({
  title,
  value,
  sub,
  className,
}: {
  title: string;
  value: string;
  sub?: string;
  className?: string;
}) {
  return (
    <div className={cn("rounded-xl border bg-card p-3 shadow-sm", className)}>
      <div className="text-[11px] font-medium text-muted-foreground">{title}</div>
      <div className="mt-1 text-lg font-semibold tabular-nums tracking-tight">{value}</div>
      {sub ? <div className="mt-0.5 text-[11px] text-muted-foreground">{sub}</div> : null}
    </div>
  );
}
