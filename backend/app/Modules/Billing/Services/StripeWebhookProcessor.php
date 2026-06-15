<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\StripeWebhookEvent;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

final class StripeWebhookProcessor
{
    public function __construct(
        private readonly StripeBillingConfig $config,
        private readonly StripeBillingService $billing,
    ) {}

    public function process(StripeEvent $event): void
    {
        if (StripeWebhookEvent::query()->whereKey($event->id)->exists()) {
            return;
        }

        match ($event->type) {
            'checkout.session.completed' => $this->billing->handleCheckoutSessionCompleted($event->data->object),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionEvent($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.payment_failed' => $this->handlePaymentFailed($event),
            default => null,
        };

        StripeWebhookEvent::query()->create([
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'processed_at' => now(),
        ]);
    }

    private function handleSubscriptionEvent(StripeEvent $event): void
    {
        $subscription = $event->data->object;
        if (! $subscription instanceof StripeSubscription) {
            return;
        }

        $tenant = $this->resolveTenantFromSubscription($subscription);
        if ($tenant === null) {
            return;
        }

        $this->billing->syncSubscriptionFromStripe($tenant, $subscription);
    }

    private function handleSubscriptionDeleted(StripeEvent $event): void
    {
        $subscription = $event->data->object;
        if (! $subscription instanceof StripeSubscription) {
            return;
        }

        $tenant = $this->resolveTenantFromSubscription($subscription);
        if ($tenant === null) {
            return;
        }

        $this->bootstrapStripe();
        $this->billing->syncSubscriptionFromStripe($tenant, $subscription);
    }

    private function handlePaymentFailed(StripeEvent $event): void
    {
        $invoice = $event->data->object;
        $customerId = (string) ($invoice->customer ?? '');
        if ($customerId === '') {
            return;
        }

        $tenant = Tenant::query()->where('stripe_customer_id', $customerId)->first();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $subscriptionId = trim((string) ($tenant->stripe_subscription_id ?? ''));
        if ($subscriptionId === '') {
            $this->subscriptions->applyPlatformUpdate($tenant, [
                'subscription_status' => TenantSubscriptionLifecycleService::STATUS_PAST_DUE,
            ]);
            $tenant->save();

            return;
        }

        $this->billing->syncSubscriptionFromStripe(
            $tenant,
            $this->retrieveSubscription($subscriptionId),
            'stripe_invoice_failed',
        );
    }

    private function resolveTenantFromSubscription(StripeSubscription $subscription): ?Tenant
    {
        $tenantId = (string) ($subscription->metadata->tenant_id ?? '');
        if ($tenantId !== '') {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $subscriptionId = (string) $subscription->id;

        return Tenant::query()->where('stripe_subscription_id', $subscriptionId)->first();
    }

    private function retrieveSubscription(string $subscriptionId): StripeSubscription
    {
        $this->bootstrapStripe();

        return StripeSubscription::retrieve($subscriptionId);
    }

    private function bootstrapStripe(): void
    {
        $secret = $this->config->secretKey();
        if ($secret === null) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        Stripe::setApiKey($secret);
    }
}
