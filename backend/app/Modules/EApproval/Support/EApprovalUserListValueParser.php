<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Parses multi-approver field values (JSON array, CSV, or single id).
 */
final class EApprovalUserListValueParser
{
    /**
     * @return list<string>
     */
    public static function parse(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_array($raw)) {
            return self::uniqueStrings(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $raw,
            ));
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '[')) {
            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }

            if (is_array($decoded)) {
                return self::uniqueStrings(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $decoded,
                ));
            }
        }

        if (str_contains($trimmed, ',')) {
            return self::uniqueStrings(array_map('trim', explode(',', $trimmed)));
        }

        return [$trimmed];
    }

    /**
     * @param  list<string>  $ids
     */
    public static function encode(array $ids): string
    {
        $clean = self::uniqueStrings($ids);
        if ($clean === []) {
            return '';
        }

        try {
            return (string) json_encode(array_values($clean), JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return implode(',', $clean);
        }
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function uniqueStrings(array $values): array
    {
        $seen = [];
        $out = [];
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }
            $seen[$trimmed] = true;
            $out[] = $trimmed;
        }

        return $out;
    }
}
