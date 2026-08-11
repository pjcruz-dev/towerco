"use client";

import { Check, X } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";
import {
  PROCUREMENT_PLAN_FEATURE_LABELS,
  type ProcurementPlanFeatureKey,
} from "@/lib/procurement/procurement-plan-features";
import { cn } from "@/lib/utils";

const ENTITLEMENT_ROWS: ProcurementPlanFeatureKey[] = [
  "enabled",
  "goods_receipt",
  "inventory",
  "ap_invoices",
  "payment_tracking",
  "rfq_sourcing",
  "vendor_contracts",
  "reporting_exports",
];

type Props = {
  features: ProcurementPlanFeatures;
  className?: string;
};

export function ProcurementEntitlementsCard({ features, className }: Props) {
  return (
    <EApprovalSectionCard
      title="Procurement-One entitlements"
      description="Capabilities included on your current plan for purchase-to-pay workflows."
      className={className}
      bodyClassName="p-0"
    >
      <ul className="divide-y divide-border">
        {ENTITLEMENT_ROWS.map((key) => {
          const enabled = Boolean(features[key]);

          return (
            <li key={key} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
              <span className="text-foreground">{PROCUREMENT_PLAN_FEATURE_LABELS[key]}</span>
              <span
                className={cn(
                  "inline-flex items-center gap-1.5 text-xs font-medium",
                  enabled ? "text-emerald-700 dark:text-emerald-300" : "text-muted-foreground",
                )}
              >
                {enabled ? (
                  <Check className="h-3.5 w-3.5" aria-hidden />
                ) : (
                  <X className="h-3.5 w-3.5" aria-hidden />
                )}
                {enabled ? "Included" : "Not included"}
              </span>
            </li>
          );
        })}
      </ul>
    </EApprovalSectionCard>
  );
}
