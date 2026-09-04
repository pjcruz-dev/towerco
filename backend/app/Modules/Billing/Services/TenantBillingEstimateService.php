<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;

/**
 * Indicative tenant invoice estimate: fixed plan base + committed add-ons above catalog bundle.
 * Amounts are returned in the platform display currency (FX applied in catalog resolution).
 */
final class TenantBillingEstimateService
{
    public function __construct(
        private readonly PlatformBillingCatalogService $catalog,
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function estimateForTenant(Tenant $tenant, int $paidSeatsUsed, int $towerLicensesUsed): array
    {
        $tier = $this->entitlements->normalizeTier($tenant->plan_tier);
        $resolved = $this->catalog->resolvedCatalog();
        $currency = (string) ($resolved['currency'] ?? 'USD');
        $tierRow = $this->tierRow($tier);
        $pricing = is_array($tierRow['pricing'] ?? null) ? $tierRow['pricing'] : [];

        $monthlyBase = max(0, (float) ($pricing['monthly_base_usd'] ?? 0));
        $perSeat = max(0, (float) ($pricing['paid_seat_overage_usd'] ?? 0));
        $perTower = max(0, (float) ($pricing['tower_overage_usd'] ?? $pricing['rfi_overage_usd'] ?? 0));

        $catalogIncludedSeats = $this->catalog->tierIncluded($tier, 'paid_seats');
        $catalogIncludedTowers = $this->catalog->tierIncluded($tier, 'tower_licenses');

        $effectiveSeats = $this->entitlements->effectiveSeatLimit($tenant);
        $paidTowerCapacity = $this->paidTowerCapacity($tenant);
        $grandfatherTowers = $this->grandfatherTowerLicenses($tenant);
        $effectiveTowerLimit = $this->entitlements->effectiveTowerLicenseLimit($tenant);

        $committedExtraSeats = max(0, $effectiveSeats - $catalogIncludedSeats);
        $committedExtraTowers = max(0, $paidTowerCapacity - $catalogIncludedTowers);
        $usageExtraSeats = max(0, $paidSeatsUsed - $effectiveSeats);
        $usageExtraTowers = max(0, $towerLicensesUsed - $effectiveTowerLimit);

        $billableExtraSeats = $committedExtraSeats + $usageExtraSeats;
        $billableExtraTowers = $committedExtraTowers + $usageExtraTowers;

        $seatAddonsMonthly = round($billableExtraSeats * $perSeat, 2);
        $towerAddonsMonthly = round($billableExtraTowers * $perTower, 2);
        $addonsMonthly = round($seatAddonsMonthly + $towerAddonsMonthly, 2);
        $estimatedMonthlyTotal = round($monthlyBase + $addonsMonthly, 2);

        $annualDiscount = $this->entitlements->effectiveAnnualDiscountPercent($tenant);
        $billingInterval = strtolower((string) ($tenant->billing_interval ?? 'monthly')) === 'annual'
            ? 'annual'
            : 'monthly';
        $annualBasePrepaid = round($monthlyBase * 12 * (1 - ($annualDiscount / 100)), 2);
        $annualAddonsEstimate = round($addonsMonthly * 12, 2);

        return [
            'currency' => $currency,
            'pricing_base_currency' => (string) ($resolved['pricing_base_currency'] ?? 'USD'),
            'billing_interval' => $billingInterval,
            'annual_discount_percent' => $annualDiscount,
            'monthly_base' => $monthlyBase,
            'catalog_included_paid_seats' => $catalogIncludedSeats,
            'catalog_included_tower_licenses' => $catalogIncludedTowers,
            'effective_paid_seats' => $effectiveSeats,
            'paid_tower_capacity' => $paidTowerCapacity,
            'grandfather_tower_licenses' => $grandfatherTowers,
            'effective_tower_licenses' => $effectiveTowerLimit,
            'committed_extra_seats' => $committedExtraSeats,
            'committed_extra_tower_licenses' => $committedExtraTowers,
            'usage_extra_seats' => $usageExtraSeats,
            'usage_extra_tower_licenses' => $usageExtraTowers,
            'billable_extra_seats' => $billableExtraSeats,
            'billable_extra_tower_licenses' => $billableExtraTowers,
            'per_paid_seat_monthly' => $perSeat,
            'per_tower_license_monthly' => $perTower,
            'add_one_paid_seat_monthly' => $perSeat,
            'add_one_tower_license_monthly' => $perTower,
            'seat_addons_monthly' => $seatAddonsMonthly,
            'tower_addons_monthly' => $towerAddonsMonthly,
            'addons_monthly' => $addonsMonthly,
            'estimated_monthly_total' => $estimatedMonthlyTotal,
            'annual_base_prepaid' => $annualBasePrepaid,
            'annual_addons_estimate' => $annualAddonsEstimate,
            'estimated_annual_total' => round($annualBasePrepaid + $annualAddonsEstimate, 2),
            'estimated_amount_due' => $billingInterval === 'annual'
                ? $annualBasePrepaid
                : $estimatedMonthlyTotal,
            'addons_billed_monthly_note' => $addonsMonthly > 0
                ? __('Add-on capacity is estimated monthly in :currency even when the plan base is annual prepay.', ['currency' => $currency])
                : null,
            // Backward-compatible keys
            'included_paid_seats' => $effectiveSeats,
            'included_tower_licenses' => $effectiveTowerLimit,
            'extra_paid_seats' => $billableExtraSeats,
            'extra_tower_licenses' => $billableExtraTowers,
            'estimated_monthly_overage' => $addonsMonthly,
            'catalog_included_rfi_units' => $catalogIncludedTowers,
            'paid_rfi_capacity' => $paidTowerCapacity,
            'grandfather_rfi_units' => $grandfatherTowers,
            'effective_rfi_units' => $effectiveTowerLimit,
            'committed_extra_rfi_units' => $committedExtraTowers,
            'usage_extra_rfi_units' => $usageExtraTowers,
            'billable_extra_rfi_units' => $billableExtraTowers,
            'per_rfi_unit_monthly' => $perTower,
            'add_one_rfi_unit_monthly' => $perTower,
            'rfi_addons_monthly' => $towerAddonsMonthly,
            'included_rfi_units' => $effectiveTowerLimit,
            'extra_rfi_units' => $billableExtraTowers,
        ];
    }

    private function paidTowerCapacity(Tenant $tenant): int
    {
        $overrides = is_array($tenant->billing_overrides) ? $tenant->billing_overrides : [];
        $tier = $this->entitlements->normalizeTier($tenant->plan_tier);

        if (array_key_exists('included_tower_licenses', $overrides)) {
            return max(0, (int) $overrides['included_tower_licenses']);
        }

        if (array_key_exists('included_rfi_units', $overrides)) {
            return max(0, (int) $overrides['included_rfi_units']);
        }

        return $this->catalog->tierIncluded($tier, 'tower_licenses');
    }

    private function grandfatherTowerLicenses(Tenant $tenant): int
    {
        $overrides = is_array($tenant->billing_overrides) ? $tenant->billing_overrides : [];

        if (array_key_exists('grandfather_tower_licenses', $overrides)) {
            return max(0, (int) $overrides['grandfather_tower_licenses']);
        }

        if (array_key_exists('grandfather_rfi_units', $overrides)) {
            return max(0, (int) $overrides['grandfather_rfi_units']);
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function tierRow(string $tier): array
    {
        foreach ($this->catalog->resolvedCatalog()['tiers'] as $row) {
            if (($row['plan_tier'] ?? '') === $tier) {
                return $row;
            }
        }

        return [];
    }
}
