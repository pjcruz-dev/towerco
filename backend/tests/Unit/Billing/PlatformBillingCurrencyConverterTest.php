<?php



declare(strict_types=1);



namespace Tests\Unit\Billing;



use App\Modules\Billing\Support\PlatformBillingCurrencyConverter;

use Tests\TestCase;



final class PlatformBillingCurrencyConverterTest extends TestCase

{

    public function test_converts_usd_list_price_to_php(): void

    {

        config([

            'billing.exchange_rates' => [

                'USD' => 1,

                'PHP' => 56,

            ],

        ]);



        $converter = new PlatformBillingCurrencyConverter();



        $this->assertSame(5544.0, $converter->convertFromUsd(99.0, 'PHP'));

    }



    public function test_converts_php_input_back_to_usd_for_storage(): void

    {

        config([

            'billing.exchange_rates' => [

                'USD' => 1,

                'PHP' => 56,

            ],

        ]);



        $converter = new PlatformBillingCurrencyConverter();



        $this->assertSame(99.0, $converter->convertToUsd(5544.0, 'PHP'));

    }

}


