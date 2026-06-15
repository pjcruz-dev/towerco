<?php



declare(strict_types=1);



namespace Tests\Unit\Billing;



use App\Models\PlatformBillingSetting;

use App\Models\Tenant;

use App\Modules\Billing\Services\TenantBillingEstimateService;

use Tests\TestCase;



final class TenantBillingEstimateServiceTest extends TestCase

{

    public function test_professional_base_plus_committed_seat_and_rfi_addons(): void

    {

        $tenant = new Tenant([

            'plan_tier' => 'professional',

            'seat_limit' => 20,

            'billing_interval' => 'monthly',

            'billing_overrides' => [

                'included_rfi_units' => 55,

            ],

        ]);

        $tenant->id = '00000000-0000-0000-0000-000000000099';



        $estimate = app(TenantBillingEstimateService::class)->estimateForTenant($tenant, 12, 10);



        $this->assertSame(99.0, $estimate['monthly_base']);

        $this->assertSame(5, $estimate['committed_extra_seats']);

        $this->assertSame(5, $estimate['committed_extra_rfi_units']);

        $this->assertSame(75.0, $estimate['seat_addons_monthly']);

        $this->assertSame(125.0, $estimate['rfi_addons_monthly']);

        $this->assertSame(299.0, $estimate['estimated_monthly_total']);

    }



    public function test_currency_conversion_applies_to_display_amounts(): void

    {

        config([

            'billing.revenue.currency' => 'PHP',

            'billing.exchange_rates' => ['USD' => 1, 'PHP' => 56],

        ]);



        $settings = PlatformBillingSetting::singleton();

        $settings->currency = 'PHP';

        if ($settings->exists) {

            $settings->save();

        }



        $tenant = new Tenant([

            'plan_tier' => 'professional',

            'seat_limit' => 15,

            'billing_interval' => 'monthly',

        ]);

        $tenant->id = '00000000-0000-0000-0000-000000000099';



        $estimate = app(TenantBillingEstimateService::class)->estimateForTenant($tenant, 10, 0);



        $this->assertSame('PHP', $estimate['currency']);

        $this->assertSame(5544.0, $estimate['monthly_base']);

        $this->assertSame(5544.0, $estimate['estimated_monthly_total']);

    }



    public function test_annual_prepay_uses_discounted_base_for_amount_due(): void

    {

        $tenant = new Tenant([

            'plan_tier' => 'professional',

            'seat_limit' => 15,

            'billing_interval' => 'annual',

        ]);

        $tenant->id = '00000000-0000-0000-0000-000000000099';



        $estimate = app(TenantBillingEstimateService::class)->estimateForTenant($tenant, 10, 0);



        $this->assertSame(950.4, $estimate['annual_base_prepaid']);

        $this->assertSame(950.4, $estimate['estimated_amount_due']);

        $this->assertSame('annual', $estimate['billing_interval']);

    }

}


