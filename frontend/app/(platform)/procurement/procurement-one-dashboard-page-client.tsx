"use client";

import Link from "next/link";
import { Building2, FileStack, FileText, Gavel, PackageCheck, Warehouse } from "lucide-react";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useProcurementOneDashboard } from "@/hooks/use-procurement-one-dashboard";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import {
  isProcurementPlanFeatureEnabled,
  procurementPlanUpgradeMessage,
} from "@/lib/procurement/procurement-plan-features";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";
import { cn } from "@/lib/utils";

type NavTile = {
  href: string;
  label: string;
  description: string;
  icon: typeof FileText;
  permission: string;
  planFeature?: keyof ProcurementPlanFeatures;
};

const NAV_TILES: NavTile[] = [
  {
    href: "/procurement/prs",
    label: "Purchase requisitions",
    description: "Create and track PRs with E-Approval workflow and project budget checks.",
    icon: FileText,
    permission: permissions.procurementOneView,
  },
  {
    href: "/procurement/pos",
    label: "Purchase orders",
    description: "Official POs with VAT totals, PR linkage, approval, and print.",
    icon: FileStack,
    permission: permissions.procurementOneView,
  },
  {
    href: "/procurement/grns",
    label: "Goods receipts",
    description: "Partial or full deliveries against POs with site GPS and photo evidence.",
    icon: PackageCheck,
    permission: permissions.procurementOneView,
    planFeature: "goods_receipt",
  },
  {
    href: "/procurement/inventory",
    label: "Inventory",
    description: "Warehouse locations, stock balances, transfers, and deploy-to-site with Asset-One.",
    icon: Warehouse,
    permission: permissions.procurementOneInventoryView,
    planFeature: "inventory",
  },
  {
    href: "/procurement/rfqs",
    label: "RFQ & sourcing",
    description: "Multi-vendor quotations, weighted bid comparison, award recommendation, and PO creation.",
    icon: Gavel,
    permission: permissions.procurementOneView,
    planFeature: "rfq_sourcing",
  },
  {
    href: "/procurement/vendors",
    label: "Vendors",
    description: "Accredited supplier registry, contacts, and accreditation history.",
    icon: Building2,
    permission: permissions.procurementOneVendorsView,
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

function NavTileGrid({
  planFeatures,
  showPlanGating,
}: {
  planFeatures?: ProcurementPlanFeatures;
  showPlanGating: boolean;
}) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {NAV_TILES.map((tile) => {
        const planEnabled = isTilePlanEnabled(tile, planFeatures);
        const gatedOff = showPlanGating && !planEnabled;
        return (
          <PermissionGate key={tile.href} requiredPermissions={[tile.permission]}>
            {gatedOff ? (
              <div className="flex flex-col rounded-xl border border-dashed border-border bg-muted/20 p-4 opacity-80">
                <tile.icon className="mb-3 h-5 w-5 text-muted-foreground" aria-hidden />
                <span className="text-base font-medium text-foreground">{tile.label}</span>
                <span className="mt-1 text-sm text-muted-foreground">{tile.description}</span>
                <span className="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                  {tilePlanLabel(tile, planFeatures)}
                </span>
                {planFeatures?.plan_tier === "professional" && tile.planFeature ? (
                  <span className="mt-1 text-xs text-muted-foreground">
                    {procurementPlanUpgradeMessage(tile.planFeature, planFeatures)}
                  </span>
                ) : null}
              </div>
            ) : (
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
            )}
          </PermissionGate>
        );
      })}
    </div>
  );
}

export function ProcurementOneDashboardPageClient() {
  const { data, isFetching, isError, error, isPlaceholderData, refetch } = useProcurementOneDashboard();
  const showSkeleton = isFetching && isPlaceholderData;
  const showDashboardData = !showSkeleton && !isError;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          title="Procurement-One"
          description={
            data?.message ??
            "Purchase requisitions, purchase orders, and goods receipts — lifecycle documents with E-Approval integration."
          }
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
            title="Could not load procurement dashboard"
            description={
              getErrorMessage(error) ||
              "Procurement-One may not be enabled on your plan. Ask your administrator to enable Procurement-One (Professional or Enterprise)."
            }
          />
        ) : null}

        {showDashboardData ? (
          <>
            <KpiStrip items={data?.kpis ?? []} />

            <NavTileGrid planFeatures={data?.plan_features} showPlanGating />
          </>
        ) : !showSkeleton && isError ? (
          <NavTileGrid showPlanGating={false} />
        ) : null}
      </div>
    </PermissionGate>
  );
}
