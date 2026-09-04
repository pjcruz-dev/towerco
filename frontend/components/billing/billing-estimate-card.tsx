"use client";

import type { TenantBillingEstimateSnapshot } from "@/lib/api/modules/admin-billing-api";
import type { TenantBillingEstimate } from "@/lib/billing/tenant-billing-estimate";
import { formatMoney } from "@/lib/billing/format-money";
import { cn } from "@/lib/utils";

type EstimateLike = TenantBillingEstimateSnapshot | TenantBillingEstimate;

type Props = {
  estimate: EstimateLike;
  className?: string;
  compact?: boolean;
};

function towerAddons(estimate: EstimateLike): number {
  return (
    ("tower_addons_monthly" in estimate ? estimate.tower_addons_monthly : undefined) ??
    ("rfi_addons_monthly" in estimate ? estimate.rfi_addons_monthly : undefined) ??
    0
  );
}

function billableExtraTowers(estimate: EstimateLike): number {
  return (
    ("billable_extra_tower_licenses" in estimate
      ? estimate.billable_extra_tower_licenses
      : undefined) ??
    ("billable_extra_rfi_units" in estimate ? estimate.billable_extra_rfi_units : undefined) ??
    0
  );
}

function perTower(estimate: EstimateLike): number {
  return (
    ("per_tower_license_monthly" in estimate ? estimate.per_tower_license_monthly : undefined) ??
    ("add_one_tower_license_monthly" in estimate
      ? estimate.add_one_tower_license_monthly
      : undefined) ??
    ("per_rfi_unit_monthly" in estimate ? estimate.per_rfi_unit_monthly : undefined) ??
    ("add_one_rfi_unit_monthly" in estimate ? estimate.add_one_rfi_unit_monthly : undefined) ??
    0
  );
}

export function BillingEstimateCard({ estimate, className, compact = false }: Props) {
  const currency = estimate.currency;
  const perSeat = estimate.per_paid_seat_monthly ?? 0;
  const towerRate = perTower(estimate);
  const towerAddonAmount = towerAddons(estimate);
  const extraTowers = billableExtraTowers(estimate);

  return (
    <div className={cn("rounded-xl border border-border bg-card p-5 shadow-sm", className)}>
      <p className="text-sm font-medium text-foreground">Estimated billing</p>
      <p className="mt-1 text-xs text-muted-foreground">
        Plan base stays fixed; add-ons apply for capacity above the catalog bundle.
      </p>

      <dl className={cn("mt-4 space-y-2 text-sm", compact && "text-xs")}>
        <div className="flex items-center justify-between gap-3">
          <dt className="text-muted-foreground">Plan base</dt>
          <dd className="font-medium tabular-nums text-foreground">
            {formatMoney(estimate.monthly_base, currency)}/mo
          </dd>
        </div>
        {estimate.seat_addons_monthly > 0 ? (
          <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground">
              Seat add-ons ({estimate.billable_extra_seats} × {formatMoney(perSeat, currency)})
            </dt>
            <dd className="font-medium tabular-nums text-foreground">
              +{formatMoney(estimate.seat_addons_monthly, currency)}/mo
            </dd>
          </div>
        ) : null}
        {towerAddonAmount > 0 ? (
          <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground">
              Tower license add-ons ({extraTowers} × {formatMoney(towerRate, currency)})
            </dt>
            <dd className="font-medium tabular-nums text-foreground">
              +{formatMoney(towerAddonAmount, currency)}/mo
            </dd>
          </div>
        ) : null}
        <div className="flex items-center justify-between gap-3 border-t border-border pt-2">
          <dt className="font-medium text-foreground">Estimated monthly total</dt>
          <dd className="text-base font-semibold tabular-nums text-foreground">
            {formatMoney(estimate.estimated_monthly_total, currency)}/mo
          </dd>
        </div>
        {estimate.billing_interval === "annual" ? (
          <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground">
              Annual prepay (base, {estimate.annual_discount_percent}% off)
            </dt>
            <dd className="font-medium tabular-nums text-foreground">
              {formatMoney(estimate.annual_base_prepaid, currency)}/yr
            </dd>
          </div>
        ) : null}
      </dl>

      <p className="mt-3 text-xs text-muted-foreground">
        +1 seat = {formatMoney(perSeat, currency)}/mo · +1 tower license ={" "}
        {formatMoney(towerRate, currency)}/mo
      </p>

      {estimate.addons_billed_monthly_note ? (
        <p className="mt-2 text-xs text-muted-foreground">{estimate.addons_billed_monthly_note}</p>
      ) : null}
    </div>
  );
}
