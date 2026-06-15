<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Core\Exceptions\StripeBillingNotAvailableException;
use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Platform\Services\TenantBillingAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Stripe;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Subscription as StripeSubscription;

final class StripeBillingService
{
    public function __construct(
        private readonly StripeBillingConfig $config,
        private readonly TenantPlanEntitlementsService $entitlements,
        private readonly TenantSubscriptionLifecycleService $subscriptions,
        private readonly TenantBillingAuditLogger $billingAudit,
    ) {}

    public function assertOperational(): void
    {
        if (! $this->config->operational()) {
            throw new StripeBillingNotAvailableException;
        }
    }

    /**
     * @return array{url: string, session_id: string}
     */
    public function createCheckoutSession(Tenant $tenant, string $planTier, TenantUser $actor): array
    {
        $this->assertOperational();

        $tier = $this->entitlements->normalizeTier($planTier);
        if (! in_array($tier, $this->config->selfServeTiers(), true)) {
            throw new StripeBillingNotAvailableException(
                __('This plan tier is not available for self-serve checkout.'),
            );
        }

        $priceId = $this->config->priceIdForTier($tier);
        if ($priceId === null) {
            throw new StripeBillingNotAvailableException(
                __('Stripe price is not configured for this plan tier.'),
            );
        }

        $this->bootstrapStripe();

        $customerId = $this->getOrCreateCustomer($tenant, $actor);

        $session = StripeCheckoutSession::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $tenant->id,
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'success_url' => $this->config->checkoutSuccessUrl(),
            'cancel_url' => $this->config->checkoutCancelUrl(),
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_tier' => $tier,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_tier' => $tier,
                ],
            ],
            'allow_promotion_codes' => true,
        ]);

        return [
            'url' => (string) $session->url,
            'session_id' => (string) $session->id,
        ];
    }

    /**
     * @return array{url: string}
     */
    public function createPortalSession(Tenant $tenant): array
    {
        $this->assertOperational();

        $customerId = trim((string) ($tenant->stripe_customer_id ?? ''));
        if ($customerId === '') {
            throw new StripeBillingNotAvailableException(
                __('No Stripe customer exists for this organization yet. Start a checkout session first.'),
            );
        }

        $this->bootstrapStripe();

        $session = StripePortalSession::create([
            'customer' => $customerId,
            'return_url' => $this->config->portalReturnUrl(),
        ]);

        return ['url' => (string) $session->url];
    }

    public function getOrCreateCustomer(Tenant $tenant, ?TenantUser $actor = null): string
    {
        $existing = trim((string) ($tenant->stripe_customer_id ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $this->bootstrapStripe();

        $domain = $tenant->domains()->first()?->domain;
        $customer = StripeCustomer::create([
            'email' => $actor?->email,
            'name' => $domain ?? (string) $tenant->id,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
            ],
        ]);

        $tenant->stripe_customer_id = (string) $customer->id;
        $tenant->save();

        return (string) $customer->id;
    }

    public function syncSubscriptionFromStripe(Tenant $tenant, StripeSubscription $subscription, string $source = 'stripe_webhook'): void
    {
        $before = $this->billingSnapshot($tenant);

        $priceId = $this->extractPriceId($subscription);
        $tier = $priceId !== null ? $this->config->tierForPriceId($priceId) : null;

        if ($tier !== null) {
            $tenant->plan_tier = $tier;
        }

        $tenant->stripe_subscription_id = (string) $subscription->id;
        $tenant->stripe_price_id = $priceId;

        $stripeStatus = (string) ($subscription->status ?? '');
        $this->applyStripeStatus($tenant, $stripeStatus, $subscription);

        DB::transaction(function () use ($tenant, $before, $source): void {
            $tenant->save();

            $after = $this->billingSnapshot($tenant);
            $changes = [];
            foreach ($before as $field => $from) {
                $to = $after[$field] ?? null;
                if ($from !== $to) {
                    $changes[$field] = ['from' => $from, 'to' => $to, 'source' => $source];
                }
            }

            $this->billingAudit->log($tenant, null, $changes);
        });
    }

    public function handleCheckoutSessionCompleted(object $session): void
    {
        $tenantId = (string) ($session->metadata->tenant_id ?? $session->client_reference_id ?? '');
        if ($tenantId === '') {
            return;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            Log::warning('Stripe checkout completed for unknown tenant', ['tenant_id' => $tenantId]);

            return;
        }

        if (! empty($session->customer)) {
            $tenant->stripe_customer_id = (string) $session->customer;
        }

        $subscriptionId = (string) ($session->subscription ?? '');
        if ($subscriptionId === '') {
            $tenant->save();

            return;
        }

        $this->bootstrapStripe();
        $subscription = StripeSubscription::retrieve($subscriptionId);
        $this->syncSubscriptionFromStripe($tenant, $subscription, 'stripe_checkout');
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentsSnapshot(Tenant $tenant): array
    {
        $base = $this->config->publicSnapshot();
        $currentTier = $this->entitlements->normalizeTier($tenant->plan_tier);
        $currentRank = $this->entitlements->tierRank($currentTier);

        $upgradeOptions = [];
        foreach ($this->config->selfServeTiers() as $tier) {
            if ($this->entitlements->tierRank($tier) <= $currentRank) {
                continue;
            }
            if ($this->config->priceIdForTier($tier) === null) {
                continue;
            }
            $upgradeOptions[] = [
                'plan_tier' => $tier,
                'label' => $this->entitlements->forTier($tier)['label'],
            ];
        }

        return [
            ...$base,
            'has_stripe_customer' => trim((string) ($tenant->stripe_customer_id ?? '')) !== '',
            'has_active_subscription' => trim((string) ($tenant->stripe_subscription_id ?? '')) !== '',
            'upgrade_options' => $upgradeOptions,
        ];
    }

    private function applyStripeStatus(Tenant $tenant, string $stripeStatus, StripeSubscription $subscription): void
    {
        match ($stripeStatus) {
            'trialing' => $this->subscriptions->applyPlatformUpdate($tenant, [
                'subscription_status' => TenantSubscriptionLifecycleService::STATUS_TRIAL,
                'trial_ends_at' => $subscription->trial_end
                    ? date('c', (int) $subscription->trial_end)
                    : null,
            ]),
            'active' => $this->subscriptions->applyPlatformUpdate($tenant, [
                'subscription_status' => TenantSubscriptionLifecycleService::STATUS_ACTIVE,
            ]),
            'past_due', 'unpaid' => $this->subscriptions->applyPlatformUpdate($tenant, [
                'subscription_status' => TenantSubscriptionLifecycleService::STATUS_PAST_DUE,
            ]),
            'canceled', 'incomplete_expired' => $this->subscriptions->applyPlatformUpdate($tenant, [
                'subscription_status' => TenantSubscriptionLifecycleService::STATUS_CANCELED,
            ]),
            default => null,
        };
    }

    private function extractPriceId(StripeSubscription $subscription): ?string
    {
        $items = $subscription->items->data ?? [];
        if ($items === []) {
            return null;
        }

        $price = $items[0]->price ?? null;
        if ($price === null) {
            return null;
        }

        return isset($price->id) ? (string) $price->id : null;
    }

    private function bootstrapStripe(): void
    {
        $secret = $this->config->secretKey();
        if ($secret === null) {
            throw new StripeBillingNotAvailableException;
        }

        Stripe::setApiKey($secret);
    }

    /**
     * @return array<string, mixed>
     */
    private function billingSnapshot(Tenant $tenant): array
    {
        return [
            'plan_tier' => (string) ($tenant->plan_tier ?? 'starter'),
            'subscription_status' => (string) ($tenant->subscription_status ?? 'active'),
            'stripe_customer_id' => $tenant->stripe_customer_id,
            'stripe_subscription_id' => $tenant->stripe_subscription_id,
            'stripe_price_id' => $tenant->stripe_price_id,
        ];
    }
}
