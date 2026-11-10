<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use Carbon\Carbon;

/**
 * Publication timing for controlled documents.
 *
 * Policies and Procedures and Work Instructions wait for mandatory training.
 * Every other type publishes on final approval.
 * The effective date is never earlier than the day the file is actually released.
 */
final class ControlledDocumentPublicationRules
{
    public static function requiresTraining(?string $raw, ?string $label = null): bool
    {
        $rawNorm = self::normalize($raw);
        $labelNorm = self::normalize($label);
        if ($rawNorm === '' && $labelNorm === '') {
            return false;
        }

        if (self::matchesPhrase($labelNorm) || self::matchesPhrase($rawNorm)) {
            return true;
        }

        if (! in_array($rawNorm, ['p', 'w'], true)) {
            return false;
        }

        return $labelNorm === '' || $labelNorm === $rawNorm;
    }

    /**
     * @param  list<string>  $types
     */
    public static function matchesListedType(?string $raw, ?string $label, array $types): bool
    {
        $needles = [];
        foreach ($types as $type) {
            $needle = strtolower(trim($type));
            if ($needle !== '') {
                $needles[] = $needle;
            }
        }
        if ($needles === []) {
            return true;
        }

        foreach ([self::normalize($raw), self::normalize($label)] as $hay) {
            if ($hay === '') {
                continue;
            }
            foreach ($needles as $needle) {
                if ($hay === $needle || (strlen($needle) >= 3 && str_contains($hay, $needle))) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function alignEffectiveDate(?Carbon $requested, Carbon $publishedAt): Carbon
    {
        $publishedDay = $publishedAt->copy()->startOfDay();
        if ($requested === null || $requested->copy()->startOfDay()->lt($publishedDay)) {
            return $publishedDay;
        }

        return $requested->copy()->startOfDay();
    }

    private static function matchesPhrase(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (str_contains($value, 'work instruction')) {
            return true;
        }

        return str_contains($value, 'polic') && str_contains($value, 'procedure');
    }

    private static function normalize(?string $value): string
    {
        $text = strtolower(trim((string) $value));
        $text = str_replace(['&', '_', '-'], ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
