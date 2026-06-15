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

    public function estimateForTenant(Tenant $tenant, int $paidSeatsUsed, int $rfiUnitsUsed): array

    {

        $tier = $this->entitlements->normalizeTier($tenant->plan_tier);

        $resolved = $this->catalog->resolvedCatalog();

        $currency = (string) ($resolved['currency'] ?? 'USD');

        $tierRow = $this->tierRow($tier);

        $pricing = is_array($tierRow['pricing'] ?? null) ? $tierRow['pricing'] : [];



        $monthlyBase = max(0, (float) ($pricing['monthly_base_usd'] ?? 0));

        $perSeat = max(0, (float) ($pricing['paid_seat_overage_usd'] ?? 0));

        $perRfi = max(0, (float) ($pricing['rfi_overage_usd'] ?? 0));



        $catalogIncludedSeats = $this->catalog->tierIncluded($tier, 'paid_seats');

        $catalogIncludedRfi = $this->catalog->tierIncluded($tier, 'rfi_units');



        $effectiveSeats = $this->entitlements->effectiveSeatLimit($tenant);

        $paidRfiCapacity = $this->paidRfiCapacity($tenant);

        $grandfatherRfi = $this->grandfatherRfiUnits($tenant);

        $effectiveRfiLimit = $this->entitlements->effectiveRfiLimit($tenant);



        $committedExtraSeats = max(0, $effectiveSeats - $catalogIncludedSeats);

        $committedExtraRfi = max(0, $paidRfiCapacity - $catalogIncludedRfi);

        $usageExtraSeats = max(0, $paidSeatsUsed - $effectiveSeats);

        $usageExtraRfi = max(0, $rfiUnitsUsed - $effectiveRfiLimit);



        $billableExtraSeats = $committedExtraSeats + $usageExtraSeats;

        $billableExtraRfi = $committedExtraRfi + $usageExtraRfi;



        $seatAddonsMonthly = round($billableExtraSeats * $perSeat, 2);

        $rfiAddonsMonthly = round($billableExtraRfi * $perRfi, 2);

        $addonsMonthly = round($seatAddonsMonthly + $rfiAddonsMonthly, 2);

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

            'catalog_included_rfi_units' => $catalogIncludedRfi,

            'effective_paid_seats' => $effectiveSeats,

            'paid_rfi_capacity' => $paidRfiCapacity,

            'grandfather_rfi_units' => $grandfatherRfi,

            'effective_rfi_units' => $effectiveRfiLimit,

            'committed_extra_seats' => $committedExtraSeats,

            'committed_extra_rfi_units' => $committedExtraRfi,

            'usage_extra_seats' => $usageExtraSeats,

            'usage_extra_rfi_units' => $usageExtraRfi,

            'billable_extra_seats' => $billableExtraSeats,

            'billable_extra_rfi_units' => $billableExtraRfi,

            'per_paid_seat_monthly' => $perSeat,

            'per_rfi_unit_monthly' => $perRfi,

            'add_one_paid_seat_monthly' => $perSeat,

            'add_one_rfi_unit_monthly' => $perRfi,

            'seat_addons_monthly' => $seatAddonsMonthly,

            'rfi_addons_monthly' => $rfiAddonsMonthly,

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

            'included_rfi_units' => $effectiveRfiLimit,

            'extra_paid_seats' => $billableExtraSeats,

            'extra_rfi_units' => $billableExtraRfi,

            'estimated_monthly_overage' => $addonsMonthly,

        ];

    }



    private function paidRfiCapacity(Tenant $tenant): int

    {

        $overrides = is_array($tenant->billing_overrides) ? $tenant->billing_overrides : [];

        $tier = $this->entitlements->normalizeTier($tenant->plan_tier);



        return isset($overrides['included_rfi_units'])

            ? max(0, (int) $overrides['included_rfi_units'])

            : $this->catalog->tierIncluded($tier, 'rfi_units');

    }



    private function grandfatherRfiUnits(Tenant $tenant): int

    {

        $overrides = is_array($tenant->billing_overrides) ? $tenant->billing_overrides : [];



        return isset($overrides['grandfather_rfi_units'])

            ? max(0, (int) $overrides['grandfather_rfi_units'])

            : 0;

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


