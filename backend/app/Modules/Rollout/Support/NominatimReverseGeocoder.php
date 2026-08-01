<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Contracts\ReverseGeocoderInterface;
use App\Modules\Rollout\DTOs\ReverseGeocodeResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NominatimReverseGeocoder implements ReverseGeocoderInterface
{
    public function providerName(): string
    {
        return 'nominatim';
    }

    public function reverse(float $latitude, float $longitude): ?ReverseGeocodeResult
    {
        $baseUrl = rtrim((string) config('geocoding.nominatim.base_url'), '/');
        $timeout = (int) config('geocoding.timeout_seconds', 8);
        $userAgent = trim((string) config('geocoding.nominatim.user_agent', 'TowerOS/1.0'));
        $email = trim((string) config('geocoding.nominatim.email', ''));
        $country = strtolower((string) config('geocoding.country', 'ph'));

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => $userAgent !== '' ? $userAgent : 'TowerOS/1.0',
                'Accept' => 'application/json',
            ])
            ->get($baseUrl.'/reverse', array_filter([
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'zoom' => 18,
                'countrycodes' => $country !== '' ? $country : null,
                'email' => $email !== '' ? $email : null,
            ], static fn ($value) => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new RuntimeException('Nominatim reverse geocode request failed (HTTP '.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        $displayName = trim((string) ($payload['display_name'] ?? ''));
        if ($displayName === '') {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];

        return new ReverseGeocodeResult(
            formattedAddress: $displayName,
            provider: $this->providerName(),
            latitude: $latitude,
            longitude: $longitude,
            components: $address,
        );
    }

    public function forward(string $query): ?ReverseGeocodeResult
    {
        $baseUrl = rtrim((string) config('geocoding.nominatim.base_url'), '/');
        $timeout = (int) config('geocoding.timeout_seconds', 8);
        $userAgent = trim((string) config('geocoding.nominatim.user_agent', 'TowerOS/1.0'));
        $email = trim((string) config('geocoding.nominatim.email', ''));
        $country = strtolower((string) config('geocoding.country', 'ph'));

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => $userAgent !== '' ? $userAgent : 'TowerOS/1.0',
                'Accept' => 'application/json',
            ])
            ->get($baseUrl.'/search', array_filter([
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 1,
                'countrycodes' => $country !== '' ? $country : null,
                'email' => $email !== '' ? $email : null,
            ], static fn ($value) => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new RuntimeException('Nominatim forward geocode request failed (HTTP '.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $hit = $payload[0] ?? null;
        if (! is_array($hit)) {
            return null;
        }

        $displayName = trim((string) ($hit['display_name'] ?? ''));
        if ($displayName === '' || ! isset($hit['lat'], $hit['lon'])) {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = is_array($hit['address'] ?? null) ? $hit['address'] : [];

        return new ReverseGeocodeResult(
            formattedAddress: $displayName,
            provider: $this->providerName(),
            latitude: (float) $hit['lat'],
            longitude: (float) $hit['lon'],
            components: $address,
        );
    }
}
