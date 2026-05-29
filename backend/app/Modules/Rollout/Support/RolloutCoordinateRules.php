<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use Illuminate\Validation\ValidationException;

final class RolloutCoordinateRules
{
    public const CAPTURE_GPS = 'gps';

    public const CAPTURE_MAP_DRAG = 'map_drag';

    public const CAPTURE_MANUAL = 'manual';

    /**
     * @return array<string, list<string>>
     */
    public static function inputRules(): array
    {
        return [
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'coordinate_capture_method' => ['sometimes', 'nullable', 'string', 'in:gps,map_drag,manual'],
            'coordinate_accuracy_m' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:50000'],
            'coordinates_captured_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Normalize lat/lng, auto-correct common swap, and require both or neither.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function applyToInput(array $input): array
    {
        $hasLat = array_key_exists('latitude', $input);
        $hasLng = array_key_exists('longitude', $input);

        if (! $hasLat && ! $hasLng) {
            return $input;
        }

        $lat = $hasLat ? self::toFloat($input['latitude']) : null;
        $lng = $hasLng ? self::toFloat($input['longitude']) : null;

        if ($lat === null && $lng === null) {
            $input['latitude'] = null;
            $input['longitude'] = null;

            return $input;
        }

        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages([
                'coordinates' => [__('Latitude and longitude must both be provided or cleared together.')],
            ]);
        }

        [$normalizedLat, $normalizedLng] = self::normalizePair($lat, $lng);

        $input['latitude'] = $normalizedLat;
        $input['longitude'] = $normalizedLng;

        return $input;
    }

    /**
     * @return array{0: float, 1: float}
     */
    public static function normalizePair(float $latitude, float $longitude): array
    {
        if (abs($latitude) > 90 && abs($longitude) <= 90) {
            return [$longitude, $latitude];
        }

        if (abs($latitude) > 90) {
            throw ValidationException::withMessages([
                'latitude' => [__('Latitude must be between -90 and 90.')],
            ]);
        }

        if (abs($longitude) > 180) {
            throw ValidationException::withMessages([
                'longitude' => [__('Longitude must be between -180 and 180.')],
            ]);
        }

        return [$latitude, $longitude];
    }

    private static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'coordinates' => [__('Coordinates must be numeric.')],
            ]);
        }

        return (float) $value;
    }
}
