<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Services\StripeBillingConfig;
use Tests\TestCase;

final class StripeBillingConfigTest extends TestCase
{
    public function test_operational_requires_enabled_keys_and_price(): void
    {
        config([
            'billing.stripe.enabled' => true,
            'billing.stripe.secret_key' => 'sk_test_x',
            'billing.stripe.publishable_key' => 'pk_test_x',
            'billing.stripe.prices.professional' => 'price_prof',
            'billing.stripe.self_serve_tiers' => ['starter', 'professional'],
        ]);

        $config = new StripeBillingConfig();

        $this->assertTrue($config->operational());
        $this->assertSame('professional', $config->tierForPriceId('price_prof'));
    }

    public function test_operational_false_when_disabled(): void
    {
        config([
            'billing.stripe.enabled' => false,
            'billing.stripe.secret_key' => 'sk_test_x',
            'billing.stripe.publishable_key' => 'pk_test_x',
            'billing.stripe.prices.professional' => 'price_prof',
        ]);

        $this->assertFalse((new StripeBillingConfig())->operational());
    }
}
