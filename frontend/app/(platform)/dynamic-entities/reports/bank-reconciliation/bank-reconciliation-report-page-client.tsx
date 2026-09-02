"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchDynFinanceReport } from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { formatPeso } from "../format-peso";

type Report = {
  message: string | null;
  selected: boolean;
  account: {
    id: string;
    name: string;
    bank_name: string;
    account_number: string;
    current_balance: number;
  } | null;
  transactions: Array<{
    id: string;
    date: string;
    reference: string;
    type: string;
    description: string;
    amount: number;
    status: string;
    reconciled: boolean;
  }>;
  summary: {
    statement_balance: number;
    book_balance: number;
    uncleared_count: number;
    uncleared_amount: number;
  };
  filter_options: {
    bank_accounts: Array<{ id: string; name: string; bank_name: string; account_number: string }>;
    periods: Array<{ id: string; label: string }>;
  };
};

export function BankReconciliationReportPageClient() {
  const [bankAccountId, setBankAccountId] = useState("");
  const [period, setPeriod] = useState("");
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load(nextAccount = bankAccountId, nextPeriod = period) {
    setLoading(true);
    try {
      const data = await fetchDynFinanceReport<Report>("bank-reconciliation", {
        bank_account_id: nextAccount || undefined,
        period: nextPeriod || undefined,
      });
      setReport(data);
      setError(null);
    } catch {
      setError("Unable to load Bank Reconciliation report.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header>
          <p className="text-sm text-muted-foreground">Operations</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">Bank Reconciliation Report</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Reconcile bank statement balances with general ledger cash accounts.
          </p>
        </header>

        <form
          className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3"
          onSubmit={(e) => {
            e.preventDefault();
            void load();
          }}
        >
          <select
            className="h-9 min-w-[240px] rounded-md border border-input bg-background px-3 text-sm"
            value={bankAccountId}
            onChange={(e) => setBankAccountId(e.target.value)}
          >
            <option value="">Select Bank Account…</option>
            {(report?.filter_options.bank_accounts ?? []).map((a) => (
              <option key={a.id} value={a.id}>
                {a.name} {a.account_number ? `(${a.account_number})` : ""}
              </option>
            ))}
          </select>
          <select
            className="h-9 min-w-[180px] rounded-md border border-input bg-background px-3 text-sm"
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
          >
            <option value="">Select Period…</option>
            {(report?.filter_options.periods ?? []).map((p) => (
              <option key={p.id} value={p.id}>{p.label}</option>
            ))}
          </select>
          <Button type="submit" size="sm" disabled={loading}>Update</Button>
        </form>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        {!report?.selected ? (
          <Card className="rounded-xl">
            <CardContent className="flex flex-col items-center justify-center gap-2 py-16 text-center">
              <p className="text-base font-medium">Select a Bank Account and Period</p>
              <p className="max-w-md text-sm text-muted-foreground">
                Choose a bank account and reconciliation period from the filters above to view the statement.
              </p>
            </CardContent>
          </Card>
        ) : (
          <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Kpi title="Account" value={report.account?.name ?? "—"} sub={report.account?.bank_name} />
              <Kpi title="Statement balance" value={formatPeso(report.summary.statement_balance)} />
              <Kpi title="Book balance" value={formatPeso(report.summary.book_balance)} />
              <Kpi
                title="Uncleared"
                value={formatPeso(report.summary.uncleared_amount)}
                sub={`${report.summary.uncleared_count} transactions`}
              />
            </div>

            <Card className="rounded-xl">
              <CardHeader>
                <CardTitle className="text-base font-medium">Bank transactions</CardTitle>
              </CardHeader>
              <CardContent className="overflow-x-auto">
                {(report.transactions.length ?? 0) === 0 ? (
                  <p className="py-8 text-center text-sm text-muted-foreground">No bank transactions for this period.</p>
                ) : (
                  <table className="min-w-full text-[13px]">
                    <thead className="text-left text-xs text-muted-foreground">
                      <tr>
                        <th className="px-2 py-2">Date</th>
                        <th className="px-2 py-2">Reference</th>
                        <th className="px-2 py-2">Type</th>
                        <th className="px-2 py-2">Description</th>
                        <th className="px-2 py-2">Status</th>
                        <th className="px-2 py-2 text-right">Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      {report.transactions.map((row) => (
                        <tr key={row.id} className="border-t border-border/70">
                          <td className="px-2 py-2">{row.date || "—"}</td>
                          <td className="px-2 py-2">
                            <Link href={`/dynamic-entities/records/${row.id}`} className="underline-offset-4 hover:underline">
                              {row.reference || row.id.slice(0, 8)}
                            </Link>
                          </td>
                          <td className="px-2 py-2">{row.type}</td>
                          <td className="px-2 py-2">{row.description || "—"}</td>
                          <td className="px-2 py-2">{row.reconciled ? "Reconciled" : row.status || "Open"}</td>
                          <td className="px-2 py-2 text-right tabular-nums">{formatPeso(row.amount)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </CardContent>
            </Card>
          </>
        )}
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
