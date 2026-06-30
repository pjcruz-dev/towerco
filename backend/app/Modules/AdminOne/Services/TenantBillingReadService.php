<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\Tenant;
use App\Modules\Billing\Support\PlatformBillingCurrencyCatalog;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use App\Modules\Billing\Services\StripeBillingService;
use App\Modules\Billing\Services\TenantBillingEstimateService;
use App\Modules\Billing\Services\TenantRfiMeterService;
use App\Modules\Billing\Services\TenantSubscriptionLifecycleService;

class TenantBillingReadService
{
    public function __construct(
        private readonly TenantSeatLimitService $seats,
        private readonly TenantPlanEntitlementsService $entitlements,
        private readonly TenantRfiMeterService $rfiMeter,
        private readonly TenantBillingEstimateService $billingEstimate,
        private readonly TenantSubscriptionLifecycleService $subscriptions,
        private readonly StripeBillingService $stripe,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $tenantKey = (string) tenant('id');
        /** @var Tenant|null $central */
        $central = Tenant::query()->find($tenantKey);

        $seatUsed = $this->seats->activeSeatCount();
        $viewerCount = $this->seats->activeViewerCount();
        $tier = $this->entitlements->normalizeTier($central?->plan_tier);
        $planSnapshot = $this->entitlements->eApprovalFeatures($tenantKey);
        $procurementSnapshot = $this->entitlements->procurementOneFeatures($tenantKey);
        $entitlements = $central !== null
            ? $this->entitlements->forTenant($central)
            : $this->entitlements->forTier($tier);
        $seatLimit = $central !== null
            ? $this->entitlements->effectiveSeatLimit($central)
            : $this->seats->seatLimit();

        $subscription = $central !== null
            ? $this->subscriptions->snapshot($central)
            : [
                'status' => 'active',
                'access_mode' => 'full',
                'access_allowed' => true,
                'message' => null,
            ];

        $catalog = $this->entitlements->catalog();
        $rfiSnapshot = $central !== null
            ? $this->rfiMeter->snapshot($central)
            : ['used' => 0, 'limit' => 0, 'available' => 0, 'metering_active' => false];
        $estimate = $central !== null
            ? $this->billingEstimate->estimateForTenant($central, $seatUsed, (int) ($rfiSnapshot['used'] ?? 0))
            : null;

        return [
            'tenant_id' => $tenantKey,
            'currency' => (string) ($catalog['currency'] ?? PlatformBillingCurrencyCatalog::platformCurrency()),
            'plan_tier' => $tier,
            'plan_label' => $entitlements['label'],
            'subscription_status' => (string) ($central?->subscription_status ?? 'active'),
            'subscription' => $subscription,
            'seat_limit' => $seatLimit,
            'seat_used' => $seatUsed,
            'viewer_seats_used' => $viewerCount,
            'seats_available' => max(0, $seatLimit - $seatUsed),
            'rfi_units' => $rfiSnapshot,
            'billing_estimate' => $estimate,
            'overage' => $estimate,
            'billing_meter_starts_at' => $central?->billing_meter_starts_at?->toIso8601String(),
            'billing_interval' => (string) ($central?->billing_interval ?? 'monthly'),
            'annual_discount_percent' => $central !== null
                ? $this->entitlements->effectiveAnnualDiscountPercent($central)
                : null,
            'entitlements' => $entitlements['modules'],
            'plan_features' => [
                'file_uploads' => $planSnapshot['file_uploads'],
                'max_file_fields' => $planSnapshot['max_file_fields'],
                'procurement_one' => $procurementSnapshot,
            ],
            'has_enterprise_overrides' => $central !== null && $this->entitlements->hasBillingOverrides($central),
            'billing_overrides' => $central?->billing_overrides,
            'plan_catalog' => $catalog,
            'support_email' => config('toweros.billing.support_email'),
            'payments' => $central !== null
                ? $this->stripe->paymentsSnapshot($central)
                : [
                    'enabled' => false,
                    'configured' => false,
                    'operational' => false,
                    'publishable_key' => null,
                    'self_serve_tiers' => [],
                    'has_stripe_customer' => false,
                    'has_active_subscription' => false,
                    'upgrade_options' => [],
                ],
        ];
    }
}
