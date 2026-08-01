<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;

/**
 * Resolves the operational geography code used for SLA holidays and TCO site IDs.
 *
 * Preference: Territory (telecom cluster) → Region (PSA / legacy free-text).
 */
final class RolloutOpsGeography
{
    public static function forProgram(RolloutProgram $program): ?string
    {
        return self::scopeCode(
            $program->territory !== null ? (string) $program->territory : null,
            $program->region !== null ? (string) $program->region : null,
        );
    }

    public static function scopeCode(?string $territory, ?string $region): ?string
    {
        $fromTerritory = self::normalize($territory);
        if ($fromTerritory !== null) {
            return $fromTerritory;
        }

        return self::normalize($region);
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = strtoupper(trim($value));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Case-insensitive compare key for holiday matching.
     */
    public static function matchKey(?string $value): ?string
    {
        $normalized = self::normalize($value);

        return $normalized !== null ? strtolower($normalized) : null;
    }
}
