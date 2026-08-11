"use client";

import Link from "next/link";
import { useState } from "react";
import { Download, FileSpreadsheet } from "lucide-react";

import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Label } from "@/components/ui/label";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import { procurementEntityCsvExportUrl, procurementExcelPackExportUrl } from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";

const CSV_ENTITIES = [
  { id: "vendors", label: "Vendors" },
  { id: "prs", label: "Purchase requisitions" },
  { id: "pr_lines", label: "PR lines" },
  { id: "pos", label: "Purchase orders" },
  { id: "po_lines", label: "PO lines" },
] as const;

export function ProcurementReportsPageClient() {
  const financePaths = useFinanceModulePaths();
  const [period, setPeriod] = useState<"current_month" | "previous_month" | "custom">("current_month");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

  const exportParams =
    period === "custom"
      ? { from: from || undefined, to: to || undefined }
      : { period: period as "current_month" | "previous_month" };

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
          }
          title="Reports & exports"
          description="Download the finance Excel pack or CSV extracts with configurable columns and date filters."
        />

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-4">
          <div className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2 md:col-span-1">
              <Label htmlFor="period">Period</Label>
              <select
                id="period"
                value={period}
                onChange={(e) => setPeriod(e.target.value as typeof period)}
                className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="current_month">Current month</option>
                <option value="previous_month">Previous month</option>
                <option value="custom">Custom range</option>
              </select>
            </div>
            {period === "custom" ? (
              <>
                <div className="space-y-2">
                  <Label htmlFor="from">From</Label>
                  <DatePicker id="from" value={from} onChange={setFrom} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="to">To</Label>
                  <DatePicker id="to" value={to} onChange={setTo} />
                </div>
              </>
            ) : null}
          </div>

          <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div className="flex items-center gap-2 text-base font-medium">
                  <FileSpreadsheet className="h-4 w-4 text-primary" aria-hidden />
                  Finance Excel pack
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                  One workbook with Vendors, PRs, PR lines, POs, and PO lines — exit criteria for monthly finance close.
                </p>
              </div>
              <Button render={<a href={procurementExcelPackExportUrl(exportParams)} download />} size="sm">
                <Download className="mr-1.5 h-4 w-4" aria-hidden />
                Download Excel
              </Button>
            </div>
          </div>
        </section>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium">CSV by entity</h2>
          <p className="mt-1 text-sm text-muted-foreground">Uses the same date filter and tenant column map from settings.</p>
          <ul className="mt-4 space-y-2">
            {CSV_ENTITIES.map((entity) => (
              <li key={entity.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                <span>{entity.label}</span>
                <Button
                  size="sm"
                  variant="outline"
                  render={
                    <a
                      href={procurementEntityCsvExportUrl(entity.id, exportParams)}
                      download
                    />
                  }
                >
                  CSV
                </Button>
              </li>
            ))}
          </ul>
        </section>

        <p className="text-sm text-muted-foreground">
          Configure export columns and scheduled finance email in{" "}
          <Link href="/settings" className="text-primary hover:underline">
            Settings
          </Link>
          .
        </p>
      </div>
    </PermissionGate>
  );
}
