<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Contracts;

use App\Modules\Rollout\DTOs\ReverseGeocodeResult;

interface ReverseGeocoderInterface
{
    public function reverse(float $latitude, float $longitude): ?ReverseGeocodeResult;

    public function forward(string $query): ?ReverseGeocodeResult;

    public function providerName(): string;
}
