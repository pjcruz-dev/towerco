<?php

declare(strict_types=1);

namespace App\Modules\Rollout\DTOs;

final class ReverseGeocodeResult
{
    /**
     * @param  array<string, mixed>  $components
     */
    public function __construct(
        public readonly string $formattedAddress,
        public readonly string $provider,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly array $components = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'formatted_address' => $this->formattedAddress,
            'provider' => $this->provider,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'components' => $this->components,
        ];
    }
}
