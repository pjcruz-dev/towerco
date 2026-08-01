<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Contracts\ReverseGeocoderInterface;
use App\Modules\Rollout\DTOs\ReverseGeocodeResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MapboxReverseGeocoder implements ReverseGeocoderInterface
{
    public function providerName(): string
    {
        return 'mapbox';
    }

    public function reverse(float $latitude, float $longitude): ?ReverseGeocodeResult
    {
        $token = trim((string) config('geocoding.mapbox.access_token', ''));
        if ($token === '' || self::isPlaceholderToken($token)) {
            throw new RuntimeException('Mapbox access token is not configured.');
        }

        $baseUrl = rtrim((string) config('geocoding.mapbox.base_url'), '/');
        $timeout = (int) config('geocoding.timeout_seconds', 8);
        $country = strtolower((string) config('geocoding.country', 'ph'));

        $url = sprintf('%s/%s,%s.json', $baseUrl, $longitude, $latitude);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($url, array_filter([
                'access_token' => $token,
                'limit' => 1,
                'language' => 'en',
                'country' => $country !== '' ? $country : null,
            ]));

        if (! $response->successful()) {
            throw new RuntimeException('Mapbox reverse geocode request failed (HTTP '.$response->status().').');
        }

        /** @var list<array<string, mixed>> $features */
        $features = $response->json('features') ?? [];
        $feature = $features[0] ?? null;
        if (! is_array($feature)) {
            return null;
        }

        $placeName = trim((string) ($feature['place_name'] ?? $feature['place_name_en'] ?? ''));
        if ($placeName === '') {
            return null;
        }

        return new ReverseGeocodeResult(
            formattedAddress: $placeName,
            provider: $this->providerName(),
            latitude: $latitude,
            longitude: $longitude,
            components: [
                'id' => $feature['id'] ?? null,
                'text' => $feature['text'] ?? null,
                'place_type' => $feature['place_type'] ?? null,
                'context' => $feature['context'] ?? [],
            ],
        );
    }

    public function forward(string $query): ?ReverseGeocodeResult
    {
        $token = trim((string) config('geocoding.mapbox.access_token', ''));
        if ($token === '' || self::isPlaceholderToken($token)) {
            throw new RuntimeException('Mapbox access token is not configured.');
        }

        $baseUrl = rtrim((string) config('geocoding.mapbox.base_url'), '/');
        $timeout = (int) config('geocoding.timeout_seconds', 8);
        $country = strtolower((string) config('geocoding.country', 'ph'));
        $encoded = rawurlencode($query);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get("{$baseUrl}/{$encoded}.json", array_filter([
                'access_token' => $token,
                'limit' => 1,
                'language' => 'en',
                'country' => $country !== '' ? $country : null,
            ]));

        if (! $response->successful()) {
            throw new RuntimeException('Mapbox forward geocode request failed (HTTP '.$response->status().').');
        }

        /** @var list<array<string, mixed>> $features */
        $features = $response->json('features') ?? [];
        $feature = $features[0] ?? null;
        if (! is_array($feature)) {
            return null;
        }

        $placeName = trim((string) ($feature['place_name'] ?? $feature['place_name_en'] ?? ''));
        $center = $feature['center'] ?? null;
        if ($placeName === '' || ! is_array($center) || count($center) < 2) {
            return null;
        }

        $longitude = (float) $center[0];
        $latitude = (float) $center[1];

        return new ReverseGeocodeResult(
            formattedAddress: $placeName,
            provider: $this->providerName(),
            latitude: $latitude,
            longitude: $longitude,
            components: [
                'id' => $feature['id'] ?? null,
                'text' => $feature['text'] ?? null,
                'place_type' => $feature['place_type'] ?? null,
                'context' => $feature['context'] ?? [],
            ],
        );
    }

    public static function isPlaceholderToken(string $token): bool
    {
        $normalized = strtolower(trim($token));

        return $normalized === ''
            || $normalized === 'pk....'
            || preg_match('/^pk\.+$/', $normalized) === 1;
    }
}
