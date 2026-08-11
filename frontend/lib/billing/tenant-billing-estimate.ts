import type { PlanCatalogTier } from "@/components/billing/plan-tier-comparison-table";

export type TenantBillingEstimate = {
  currency: string;
  billing_interval: "monthly" | "annual";
  annual_discount_percent: number;
  monthly_base: number;
  catalog_included_paid_seats: number;
  catalog_included_rfi_units: number;
  effective_paid_seats: number;
  paid_rfi_capacity: number;
  grandfather_rfi_units?: number;
  committed_extra_seats: number;
  committed_extra_rfi_units: number;
  billable_extra_seats: number;
  billable_extra_rfi_units: number;
  per_paid_seat_monthly: number;
  per_rfi_unit_monthly: number;
  seat_addons_monthly: number;
  rfi_addons_monthly: number;
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
  includedRfiUnitsOverride?: number | null;
  grandfatherRfiUnits?: number;
  seatUsed?: number;
  rfiUsed?: number;
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
  const perRfi = Math.max(0, tier.pricing?.rfi_overage_usd ?? 0);

  const catalogIncludedSeats = tier.included?.paid_seats ?? 0;
  const catalogIncludedRfi = tier.included?.rfi_units ?? 0;

  const effectiveSeats = Math.max(1, input.effectiveSeatLimit);
  const paidRfiCapacity =
    input.includedRfiUnitsOverride != null
      ? Math.max(0, input.includedRfiUnitsOverride)
      : catalogIncludedRfi;
  const grandfatherRfi = Math.max(0, input.grandfatherRfiUnits ?? 0);
  const effectiveRfiLimit = paidRfiCapacity + grandfatherRfi;

  const committedExtraSeats = Math.max(0, effectiveSeats - catalogIncludedSeats);
  const committedExtraRfi = Math.max(0, paidRfiCapacity - catalogIncludedRfi);
  const usageExtraSeats = Math.max(0, (input.seatUsed ?? 0) - effectiveSeats);
  const usageExtraRfi = Math.max(0, (input.rfiUsed ?? 0) - effectiveRfiLimit);

  const billableExtraSeats = committedExtraSeats + usageExtraSeats;
  const billableExtraRfi = committedExtraRfi + usageExtraRfi;

  const seatAddonsMonthly = Math.round(billableExtraSeats * perSeat * 100) / 100;
  const rfiAddonsMonthly = Math.round(billableExtraRfi * perRfi * 100) / 100;
  const addonsMonthly = Math.round((seatAddonsMonthly + rfiAddonsMonthly) * 100) / 100;
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
    catalog_included_rfi_units: catalogIncludedRfi,
    effective_paid_seats: effectiveSeats,
    paid_rfi_capacity: paidRfiCapacity,
    grandfather_rfi_units: grandfatherRfi,
    committed_extra_seats: committedExtraSeats,
    committed_extra_rfi_units: committedExtraRfi,
    billable_extra_seats: billableExtraSeats,
    billable_extra_rfi_units: billableExtraRfi,
    per_paid_seat_monthly: perSeat,
    per_rfi_unit_monthly: perRfi,
    seat_addons_monthly: seatAddonsMonthly,
    rfi_addons_monthly: rfiAddonsMonthly,
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
