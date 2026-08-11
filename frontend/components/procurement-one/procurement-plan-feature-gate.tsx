"use client";

import Link from "next/link";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { useProcurementPlanFeatures } from "@/hooks/use-procurement-plan-features";
import {
  isProcurementPlanFeatureEnabled,
  procurementPlanUpgradeMessage,
  type ProcurementPlanFeatureKey,
} from "@/lib/procurement/procurement-plan-features";

type Props = {
  feature: ProcurementPlanFeatureKey;
  children: React.ReactNode;
};

export function ProcurementPlanFeatureGate({ feature, children }: Props) {
  const query = useProcurementPlanFeatures();

  if (query.isLoading) {
    return <DashboardContentSkeleton />;
  }

  if (query.isError || !isProcurementPlanFeatureEnabled(query.data, feature)) {
    const tier = query.data?.plan_tier ?? "starter";

    return (
      <div className="mx-auto max-w-3xl space-y-4 p-6 md:p-8">
        <OperationalAlert
          level="warning"
          title="Feature not available on your plan"
          description={procurementPlanUpgradeMessage(feature, query.data)}
        />
        {tier !== "enterprise" ? (
          <p className="text-sm text-muted-foreground">
            Review your entitlements on the{" "}
            <Link href="/billing" className="font-medium text-primary underline-offset-2 hover:underline">
              Billing &amp; subscription
            </Link>{" "}
            page or contact your administrator.
          </p>
        ) : null}
      </div>
    );
  }

  return <>{children}</>;
}
