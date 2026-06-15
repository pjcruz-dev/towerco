<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

final class StripeBillingConfig
{
    public function enabled(): bool
    {
        return (bool) config('billing.stripe.enabled', false);
    }

    public function secretKey(): ?string
    {
        $key = trim((string) config('billing.stripe.secret_key', ''));

        return $key !== '' ? $key : null;
    }

    public function publishableKey(): ?string
    {
        $key = trim((string) config('billing.stripe.publishable_key', ''));

        return $key !== '' ? $key : null;
    }

    public function webhookSecret(): ?string
    {
        $secret = trim((string) config('billing.stripe.webhook_secret', ''));

        return $secret !== '' ? $secret : null;
    }

    /**
     * Stripe is configured enough to call the API (keys present).
     */
    public function configured(): bool
    {
        return $this->secretKey() !== null && $this->publishableKey() !== null;
    }

    /**
     * Self-serve checkout is available (enabled + keys + at least one price).
     */
    public function operational(): bool
    {
        if (! $this->enabled() || ! $this->configured()) {
            return false;
        }

        foreach ($this->selfServeTiers() as $tier) {
            if ($this->priceIdForTier($tier) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function selfServeTiers(): array
    {
        /** @var list<string> $tiers */
        $tiers = config('billing.stripe.self_serve_tiers', ['starter', 'professional']);

        return array_values(array_filter(
            $tiers,
            static fn (string $tier): bool => in_array($tier, ['starter', 'professional', 'enterprise'], true),
        ));
    }

    public function priceIdForTier(string $planTier): ?string
    {
        $tier = strtolower(trim($planTier));
        $priceId = config("billing.stripe.prices.{$tier}");

        if (! is_string($priceId) || trim($priceId) === '') {
            return null;
        }

        return trim($priceId);
    }

    public function tierForPriceId(string $priceId): ?string
    {
        $needle = trim($priceId);

        foreach (['starter', 'professional', 'enterprise'] as $tier) {
            if ($this->priceIdForTier($tier) === $needle) {
                return $tier;
            }
        }

        return null;
    }

    public function checkoutSuccessUrl(): string
    {
        return $this->tenantAppUrl((string) config('billing.stripe.checkout_success_path', '/billing?checkout=success'));
    }

    public function checkoutCancelUrl(): string
    {
        return $this->tenantAppUrl((string) config('billing.stripe.checkout_cancel_path', '/billing?checkout=canceled'));
    }

    public function portalReturnUrl(): string
    {
        return $this->tenantAppUrl((string) config('billing.stripe.portal_return_path', '/billing'));
    }

    private function tenantAppUrl(string $path): string
    {
        $base = rtrim((string) config('toweros.tenant_app_url', config('toweros.frontend_app_url', '')), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSnapshot(): array
    {
        return [
            'enabled' => $this->enabled(),
            'configured' => $this->configured(),
            'operational' => $this->operational(),
            'publishable_key' => $this->operational() ? $this->publishableKey() : null,
            'self_serve_tiers' => $this->selfServeTiers(),
        ];
    }
}
