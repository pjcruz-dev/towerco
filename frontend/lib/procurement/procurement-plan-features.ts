import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";

export type ProcurementPlanFeatureKey = Exclude<keyof ProcurementPlanFeatures, "plan_tier">;

export const PROCUREMENT_PLAN_FEATURE_LABELS: Record<ProcurementPlanFeatureKey, string> = {
  enabled: "Procurement-One module",
  goods_receipt: "Goods receipt",
  advanced_numbering: "Advanced numbering",
  inventory: "Inventory",
  ap_invoices: "AP invoices",
  payment_tracking: "Payment tracking",
  rfq_sourcing: "RFQ & sourcing",
  vendor_contracts: "Vendor contracts",
  reporting_exports: "Reports & exports",
};

export function isProcurementPlanFeatureEnabled(
  planFeatures: ProcurementPlanFeatures | undefined,
  feature: ProcurementPlanFeatureKey,
): boolean {
  if (!planFeatures) {
    return true;
  }

  return Boolean(planFeatures[feature]);
}

export function procurementPlanUpgradeMessage(
  feature: ProcurementPlanFeatureKey,
  planFeatures?: ProcurementPlanFeatures,
): string {
  const label = PROCUREMENT_PLAN_FEATURE_LABELS[feature];
  const tier = planFeatures?.plan_tier ?? "starter";

  if (tier === "enterprise") {
    return `${label} is not enabled for your organization. Contact your TowerOS account team if you expected this capability.`;
  }

  if (tier === "professional") {
    return `${label} requires the Enterprise plan. Upgrade billing or contact support to unlock procure-to-pay features.`;
  }

  return `${label} requires the Professional or Enterprise plan with Procurement-One enabled.`;
}
