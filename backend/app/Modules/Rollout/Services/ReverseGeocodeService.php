<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Contracts\ReverseGeocoderInterface;
use App\Modules\Rollout\DTOs\ReverseGeocodeResult;
use App\Modules\Rollout\Support\MapboxReverseGeocoder;
use App\Modules\Rollout\Support\NominatimReverseGeocoder;
use App\Modules\Rollout\Support\RolloutCoordinateRules;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ReverseGeocodeService
{
    public function __construct(
        private readonly ReverseGeocoderInterface $geocoder,
    ) {}

    public function reverse(float $latitude, float $longitude): ReverseGeocodeResult
    {
        [$latitude, $longitude] = RolloutCoordinateRules::normalizePair($latitude, $longitude);

        try {
            $result = $this->geocoder->reverse($latitude, $longitude);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'coordinates' => [__('Could not resolve an address for these coordinates. Try again shortly.')],
            ]);
        }

        if ($result === null || trim($result->formattedAddress) === '') {
            throw ValidationException::withMessages([
                'coordinates' => [__('No address found for these coordinates.')],
            ]);
        }

        return $result;
    }

    public function forward(string $query): ReverseGeocodeResult
    {
        $query = trim($query);
        if ($query === '') {
            throw ValidationException::withMessages([
                'query' => [__('Enter an address to locate.')],
            ]);
        }

        if (mb_strlen($query) < 3) {
            throw ValidationException::withMessages([
                'query' => [__('Enter at least 3 characters for the address.')],
            ]);
        }

        try {
            $result = $this->geocoder->forward($query);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'query' => [__('Could not locate this address. Try again shortly.')],
            ]);
        }

        if ($result === null) {
            throw ValidationException::withMessages([
                'query' => [__('No coordinates found for this address.')],
            ]);
        }

        [$latitude, $longitude] = RolloutCoordinateRules::normalizePair(
            $result->latitude,
            $result->longitude,
        );

        return new ReverseGeocodeResult(
            formattedAddress: $result->formattedAddress !== '' ? $result->formattedAddress : $query,
            provider: $result->provider,
            latitude: $latitude,
            longitude: $longitude,
            components: $result->components,
        );
    }

    public function providerName(): string
    {
        return $this->geocoder->providerName();
    }

    /**
     * Resolve the configured geocoder implementation.
     */
    public static function resolveDriver(): ReverseGeocoderInterface
    {
        $driver = strtolower(trim((string) config('geocoding.driver', 'auto')));
        $token = self::configuredMapboxToken();

        return match ($driver) {
            'mapbox' => self::requireMapbox($token),
            'nominatim' => app(NominatimReverseGeocoder::class),
            'auto' => $token !== ''
                ? app(MapboxReverseGeocoder::class)
                : app(NominatimReverseGeocoder::class),
            default => throw new RuntimeException('Unsupported geocoding driver: '.$driver),
        };
    }

    public static function configuredMapboxToken(): string
    {
        $token = trim((string) config('geocoding.mapbox.access_token', ''));

        return MapboxReverseGeocoder::isPlaceholderToken($token) ? '' : $token;
    }

    private static function requireMapbox(string $token): ReverseGeocoderInterface
    {
        if ($token === '') {
            throw new RuntimeException('GEOCODING_DRIVER=mapbox requires a real MAPBOX_ACCESS_TOKEN.');
        }

        return app(MapboxReverseGeocoder::class);
    }
}
