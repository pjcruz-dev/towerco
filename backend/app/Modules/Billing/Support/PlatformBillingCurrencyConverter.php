<?php



declare(strict_types=1);



namespace App\Modules\Billing\Support;



final class PlatformBillingCurrencyConverter

{

    /** @var array<string, float> */

    private array $rates;



    public function __construct()

    {

        /** @var array<string, float|int|string> $configured */

        $configured = config('billing.exchange_rates', []);

        $this->rates = ['USD' => 1.0];



        foreach ($configured as $code => $rate) {

            $normalized = strtoupper(trim((string) $code));

            if ($normalized === '' || ! is_numeric($rate)) {

                continue;

            }

            $this->rates[$normalized] = max(0.000001, (float) $rate);

        }

    }



    public function baseCurrency(): string

    {

        return 'USD';

    }



    /**

     * @return array<string, float> ISO code => units per 1 USD

     */

    public function rates(): array

    {

        return $this->rates;

    }



    public function rateFor(string $currency): float

    {

        $code = strtoupper(trim($currency));



        return $this->rates[$code] ?? 1.0;

    }



    public function convertFromUsd(float $amountUsd, string $toCurrency): float

    {

        if ($amountUsd <= 0) {

            return 0.0;

        }



        return $this->roundForCurrency($amountUsd * $this->rateFor($toCurrency), $toCurrency);

    }



    public function convertToUsd(float $amount, string $fromCurrency): float

    {

        if ($amount <= 0) {

            return 0.0;

        }



        $code = strtoupper(trim($fromCurrency));

        if ($code === 'USD') {

            return round($amount, 2);

        }



        return round($amount / $this->rateFor($code), 2);

    }



    /**

     * @param  array<string, float>  $pricingUsd

     * @return array<string, float>

     */

    public function convertPricingFromUsd(array $pricingUsd, string $toCurrency): array

    {

        $out = [];

        foreach ($pricingUsd as $key => $value) {

            $out[$key] = $this->convertFromUsd((float) $value, $toCurrency);

        }



        return $out;

    }



    /**

     * @param  array<string, float|int|string>  $pricing

     * @return array<string, float>

     */

    public function convertPricingToUsd(array $pricing, string $fromCurrency): array

    {

        $out = [];

        foreach ($pricing as $key => $value) {

            if (! is_numeric($value)) {

                continue;

            }

            $out[(string) $key] = $this->convertToUsd((float) $value, $fromCurrency);

        }



        return $out;

    }



    public function roundForCurrency(float $amount, string $currency): float

    {

        $code = strtoupper(trim($currency));



        return match ($code) {

            'JPY', 'IDR', 'VND' => round($amount, 0),

            'PHP', 'THB', 'INR', 'KRW' => round($amount, 0),

            default => round($amount, 2),

        };

    }

}


