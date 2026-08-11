"use client";

import Link from "next/link";
import { Banknote, BarChart3, FileSignature, FileSpreadsheet, PiggyBank } from "lucide-react";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import type { ProjectOneKpi } from "@/modules/project-one/types";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useProcurementOneDashboard } from "@/hooks/use-procurement-one-dashboard";
import { getErrorMessage } from "@/lib/api/error";
import { financeOneRoutes } from "@/lib/navigation/finance-one-routes";
import { permissions } from "@/lib/rbac/permissions";
import {
  isProcurementPlanFeatureEnabled,
  procurementPlanUpgradeMessage,
  type ProcurementPlanFeatureKey,
} from "@/lib/procurement/procurement-plan-features";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";
import { cn } from "@/lib/utils";

type NavTile = {
  href: string;
  label: string;
  description: string;
  icon: typeof PiggyBank;
  permission: string;
  planFeature?: ProcurementPlanFeatureKey;
};

const NAV_TILES: NavTile[] = [
  {
    href: financeOneRoutes.budget,
    label: "Budget & encumbrance",
    description: "Rollout budget lines, cost centers, and live PR + PO commitment against available budget.",
    icon: PiggyBank,
    permission: permissions.financeOneView,
  },
  {
    href: financeOneRoutes.apInvoices,
    label: "AP invoices",
    description: "2-way / 3-way match supplier invoices to PO and GRN, approve for payment, GL export.",
    icon: FileSpreadsheet,
    permission: permissions.financeOneView,
    planFeature: "ap_invoices",
  },
  {
    href: financeOneRoutes.payments,
    label: "Payment tracking",
    description: "Payment requests, batch export for finance, and scheduled → paid → reconciled status.",
    icon: Banknote,
    permission: permissions.financeOneView,
    planFeature: "payment_tracking",
  },
  {
    href: financeOneRoutes.contracts,
    label: "Vendor contracts",
    description: "Long-term vendor agreements, spend ceilings vs PO totals, expiry alerts, and Legal folder linkage.",
    icon: FileSignature,
    permission: permissions.financeOneView,
    planFeature: "vendor_contracts",
  },
  {
    href: financeOneRoutes.reports,
    label: "Reports & exports",
    description: "Finance Excel pack, CSV extracts, P2P cycle metrics, vendor spend, and scheduled email delivery.",
    icon: BarChart3,
    permission: permissions.financeOneReportsView,
    planFeature: "reporting_exports",
  },
];

function isTilePlanEnabled(tile: NavTile, planFeatures?: ProcurementPlanFeatures): boolean {
  if (!tile.planFeature || !planFeatures) {
    return true;
  }

  return isProcurementPlanFeatureEnabled(planFeatures, tile.planFeature);
}

function tilePlanLabel(tile: NavTile, planFeatures?: ProcurementPlanFeatures): string {
  if (!tile.planFeature) {
    return "";
  }

  const tier = planFeatures?.plan_tier ?? "starter";
  if (tier === "enterprise") {
    return "Not enabled on your plan";
  }

  return "Requires Enterprise plan";
}

function normalizeKpis(
  items: Array<{ key: string; label: string; value: string | number; tone?: string }> | undefined,
): ProjectOneKpi[] {
  return (items ?? []).map((item) => ({
    ...item,
    value: String(item.value),
    tone: item.tone as ProjectOneKpi["tone"],
  }));
}

export function FinanceOneDashboardPageClient() {
  const { data, isFetching, isError, error, isPlaceholderData, refetch } = useProcurementOneDashboard();
  const showSkeleton = isFetching && isPlaceholderData;
  const showDashboardData = !showSkeleton && !isError;

  return (
    <PermissionGate requiredPermissions={[permissions.financeOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          title="Finance-One"
          description="Budget, accounts payable, payment tracking, vendor contracts, and finance exports — linked to Procurement-One documents."
          actions={
            <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
              {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
              Refresh
            </Button>
          }
        />

        {showSkeleton ? <DashboardContentSkeleton /> : null}
        {isError ? (
          <OperationalAlert
            level="error"
            title="Could not load finance dashboard"
            description={
              getErrorMessage(error) ||
              "Finance-One requires Procurement-One on your plan. Ask your administrator to enable Procurement-One (Professional or Enterprise)."
            }
          />
        ) : null}

        {showDashboardData ? (
          <>
            {(data?.budget_kpis?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <h2 className="text-base font-medium text-foreground">Budget utilization</h2>
                <KpiStrip items={normalizeKpis(data?.budget_kpis)} />
              </section>
            ) : null}
            {(data?.ap_kpis?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <h2 className="text-base font-medium text-foreground">Accounts payable</h2>
                <KpiStrip items={normalizeKpis(data?.ap_kpis)} />
              </section>
            ) : null}
            {(data?.payment_kpis?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <h2 className="text-base font-medium text-foreground">Payment tracking</h2>
                <KpiStrip items={normalizeKpis(data?.payment_kpis)} />
              </section>
            ) : null}
            {(data?.contract_kpis?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <h2 className="text-base font-medium text-foreground">Vendor contracts</h2>
                <KpiStrip items={normalizeKpis(data?.contract_kpis)} />
              </section>
            ) : null}
            {(data?.p2p?.kpis?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <h2 className="text-base font-medium text-foreground">Procure-to-pay</h2>
                <KpiStrip items={normalizeKpis(data?.p2p?.kpis)} />
              </section>
            ) : null}
            {(data?.vendor_spend?.rows?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <h2 className="text-base font-medium text-foreground">
                  Vendor spend — {data?.vendor_spend?.period_label}
                </h2>
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                  <table className="min-w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                      <tr>
                        <th className="px-4 py-2 font-medium">Vendor</th>
                        <th className="px-4 py-2 font-medium text-right">POs</th>
                        <th className="px-4 py-2 font-medium text-right">Spend</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data?.vendor_spend?.rows.map((row) => (
                        <tr key={`${row.vendor_code}-${row.vendor_name}`} className="border-t border-border">
                          <td className="px-4 py-2">{row.vendor_name ?? row.vendor_code ?? "—"}</td>
                          <td className="px-4 py-2 text-right tabular-nums">{row.po_count}</td>
                          <td className="px-4 py-2 text-right tabular-nums">
                            {row.currency_code ?? "PHP"}{" "}
                            {row.total_spend.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </section>
            ) : null}

            <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {NAV_TILES.map((tile) => {
                const planEnabled = isTilePlanEnabled(tile, data?.plan_features);
                return (
                  <PermissionGate key={tile.href} requiredPermissions={[tile.permission]}>
                    {planEnabled ? (
                      <Link
                        href={tile.href}
                        className={cn(
                          "group flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm transition-colors",
                          "hover:border-primary/30 hover:bg-muted/20",
                        )}
                      >
                        <tile.icon className="mb-3 h-5 w-5 text-primary" aria-hidden />
                        <span className="text-base font-medium text-foreground">{tile.label}</span>
                        <span className="mt-1 text-sm text-muted-foreground">{tile.description}</span>
                      </Link>
                    ) : (
                      <div className="flex flex-col rounded-xl border border-dashed border-border bg-muted/20 p-4 opacity-80">
                        <tile.icon className="mb-3 h-5 w-5 text-muted-foreground" aria-hidden />
                        <span className="text-base font-medium text-foreground">{tile.label}</span>
                        <span className="mt-1 text-sm text-muted-foreground">{tile.description}</span>
                        <span className="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                          {tilePlanLabel(tile, data?.plan_features)}
                        </span>
                        {data?.plan_features?.plan_tier === "professional" && tile.planFeature ? (
                          <span className="mt-1 text-xs text-muted-foreground">
                            {procurementPlanUpgradeMessage(tile.planFeature, data.plan_features)}
                          </span>
                        ) : null}
                      </div>
                    )}
                  </PermissionGate>
                );
              })}
            </section>
          </>
        ) : !showSkeleton && isError ? (
          <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {NAV_TILES.map((tile) => (
              <PermissionGate key={tile.href} requiredPermissions={[tile.permission]}>
                <Link
                  href={tile.href}
                  className={cn(
                    "group flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm transition-colors",
                    "hover:border-primary/30 hover:bg-muted/20",
                  )}
                >
                  <tile.icon className="mb-3 h-5 w-5 text-primary" aria-hidden />
                  <span className="text-base font-medium text-foreground">{tile.label}</span>
                  <span className="mt-1 text-sm text-muted-foreground">{tile.description}</span>
                </Link>
              </PermissionGate>
            ))}
          </section>
        ) : null}
      </div>
    </PermissionGate>
  );
}
