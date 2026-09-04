import type { PlanCatalogTier } from "@/components/billing/plan-tier-comparison-table";

export type TenantBillingEstimate = {
  currency: string;
  billing_interval: "monthly" | "annual";
  annual_discount_percent: number;
  monthly_base: number;
  catalog_included_paid_seats: number;
  catalog_included_tower_licenses: number;
  effective_paid_seats: number;
  paid_tower_capacity: number;
  grandfather_tower_licenses?: number;
  committed_extra_seats: number;
  committed_extra_tower_licenses: number;
  billable_extra_seats: number;
  billable_extra_tower_licenses: number;
  per_paid_seat_monthly: number;
  per_tower_license_monthly: number;
  seat_addons_monthly: number;
  tower_addons_monthly: number;
  addons_monthly: number;
  estimated_monthly_total: number;
  annual_base_prepaid: number;
  annual_addons_estimate: number;
  estimated_annual_total: number;
  estimated_amount_due: number;
  addons_billed_monthly_note: string | null;
};

type ComputeInput = {
  planTier: string;
  currency: string;
  catalogTiers: PlanCatalogTier[];
  defaultAnnualDiscountPercent?: number;
  billingInterval?: "monthly" | "annual";
  annualDiscountOverride?: number | null;
  effectiveSeatLimit: number;
  includedTowerLicensesOverride?: number | null;
  grandfatherTowerLicenses?: number;
  seatUsed?: number;
  towerLicensesUsed?: number;
};

function tierRow(tiers: PlanCatalogTier[], planTier: string): PlanCatalogTier | undefined {
  return tiers.find((tier) => tier.plan_tier === planTier);
}

export function computeTenantBillingEstimate(input: ComputeInput): TenantBillingEstimate | null {
  const tier = tierRow(input.catalogTiers, input.planTier);
  if (!tier) {
    return null;
  }

  const monthlyBase = Math.max(0, tier.pricing?.monthly_base_usd ?? 0);
  const perSeat = Math.max(0, tier.pricing?.paid_seat_overage_usd ?? 0);
  const perTower = Math.max(
    0,
    tier.pricing?.tower_overage_usd ?? tier.pricing?.rfi_overage_usd ?? 0,
  );

  const catalogIncludedSeats = tier.included?.paid_seats ?? 0;
  const catalogIncludedTowers =
    tier.included?.tower_licenses ?? tier.included?.rfi_units ?? 0;

  const effectiveSeats = Math.max(1, input.effectiveSeatLimit);
  const paidTowerCapacity =
    input.includedTowerLicensesOverride != null
      ? Math.max(0, input.includedTowerLicensesOverride)
      : catalogIncludedTowers;
  const grandfatherTowers = Math.max(0, input.grandfatherTowerLicenses ?? 0);
  const effectiveTowerLimit = paidTowerCapacity + grandfatherTowers;

  const committedExtraSeats = Math.max(0, effectiveSeats - catalogIncludedSeats);
  const committedExtraTowers = Math.max(0, paidTowerCapacity - catalogIncludedTowers);
  const usageExtraSeats = Math.max(0, (input.seatUsed ?? 0) - effectiveSeats);
  const usageExtraTowers = Math.max(0, (input.towerLicensesUsed ?? 0) - effectiveTowerLimit);

  const billableExtraSeats = committedExtraSeats + usageExtraSeats;
  const billableExtraTowers = committedExtraTowers + usageExtraTowers;

  const seatAddonsMonthly = Math.round(billableExtraSeats * perSeat * 100) / 100;
  const towerAddonsMonthly = Math.round(billableExtraTowers * perTower * 100) / 100;
  const addonsMonthly = Math.round((seatAddonsMonthly + towerAddonsMonthly) * 100) / 100;
  const estimatedMonthlyTotal = Math.round((monthlyBase + addonsMonthly) * 100) / 100;

  const annualDiscount =
    input.annualDiscountOverride != null
      ? input.annualDiscountOverride
      : (input.defaultAnnualDiscountPercent ?? 20);
  const billingInterval = input.billingInterval ?? "monthly";
  const annualBasePrepaid = Math.round(monthlyBase * 12 * (1 - annualDiscount / 100) * 100) / 100;
  const annualAddonsEstimate = Math.round(addonsMonthly * 12 * 100) / 100;

  return {
    currency: input.currency,
    billing_interval: billingInterval,
    annual_discount_percent: annualDiscount,
    monthly_base: monthlyBase,
    catalog_included_paid_seats: catalogIncludedSeats,
    catalog_included_tower_licenses: catalogIncludedTowers,
    effective_paid_seats: effectiveSeats,
    paid_tower_capacity: paidTowerCapacity,
    grandfather_tower_licenses: grandfatherTowers,
    committed_extra_seats: committedExtraSeats,
    committed_extra_tower_licenses: committedExtraTowers,
    billable_extra_seats: billableExtraSeats,
    billable_extra_tower_licenses: billableExtraTowers,
    per_paid_seat_monthly: perSeat,
    per_tower_license_monthly: perTower,
    seat_addons_monthly: seatAddonsMonthly,
    tower_addons_monthly: towerAddonsMonthly,
    addons_monthly: addonsMonthly,
    estimated_monthly_total: estimatedMonthlyTotal,
    annual_base_prepaid: annualBasePrepaid,
    annual_addons_estimate: annualAddonsEstimate,
    estimated_annual_total: Math.round((annualBasePrepaid + annualAddonsEstimate) * 100) / 100,
    estimated_amount_due:
      billingInterval === "annual" ? annualBasePrepaid : estimatedMonthlyTotal,
    addons_billed_monthly_note:
      addonsMonthly > 0
        ? `Add-on capacity is estimated monthly in ${input.currency} even when the plan base is annual prepay.`
        : null,
  };
}
