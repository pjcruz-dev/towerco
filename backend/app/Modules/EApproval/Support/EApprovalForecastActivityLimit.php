<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Cash-advance forecast lines only. Liquidation and reimbursement keep a typed actual,
 * so a long activity there is not this cap.
 */
final class EApprovalForecastActivityLimit
{
    public const MAX_DAYS = 15;

    /**
     * @param  list<string>  $labels
     */
    public static function error(string $fieldName, array $labels, mixed $raw): ?string
    {
        if ($fieldName !== 'expense_lines' || self::keepsTypedAmount($labels)) {
            return null;
        }

        $dateIndex = EApprovalPerDiemDays::dateLabelIndex($labels);
        if ($dateIndex === null) {
            return null;
        }

        $earliest = null;
        $latest = null;
        foreach (self::rows($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cell = trim((string) ($row[(string) $dateIndex] ?? $row[$dateIndex] ?? ''));
            if ($cell === '') {
                continue;
            }
            [$from, $to] = self::rangeEnds($cell);
            $fromDay = substr($from, 0, 10);
            $toDay = substr($to, 0, 10);
            if (EApprovalPerDiemDays::inclusiveDays($fromDay, $toDay) === null) {
                continue;
            }
            if ($earliest === null || $fromDay < $earliest) {
                $earliest = $fromDay;
            }
            if ($latest === null || $toDay > $latest) {
                $latest = $toDay;
            }
        }

        if ($earliest === null || $latest === null) {
            return null;
        }

        $span = EApprovalPerDiemDays::inclusiveDays($earliest, $latest);
        if ($span !== null && $span > self::MAX_DAYS) {
            return __('Forecast activities can cover at most :days days, from the earliest start to the latest end.', [
                'days' => self::MAX_DAYS,
            ]);
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     */
    private static function keepsTypedAmount(array $labels): bool
    {
        $normalized = array_map(static fn (string $label): string => strtolower(trim($label)), $labels);

        return in_array('forecast amount', $normalized, true) || in_array('or no', $normalized, true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function rangeEnds(string $cell): array
    {
        $decoded = json_decode($cell, true);
        if (is_array($decoded)) {
            return [trim((string) ($decoded['from'] ?? '')), trim((string) ($decoded['to'] ?? ''))];
        }
        if (str_contains($cell, '|')) {
            $parts = explode('|', $cell, 2);

            return [trim($parts[0]), trim($parts[1] ?? '')];
        }

        return ['', ''];
    }

    /**
     * @return list<mixed>
     */
    private static function rows(mixed $raw): array
    {
        if (is_array($raw)) {
            if (array_is_list($raw)) {
                return $raw;
            }
            $rows = $raw['rows'] ?? null;

            return is_array($rows) ? array_values($rows) : [];
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }
        if (array_is_list($decoded)) {
            return $decoded;
        }
        $rows = $decoded['rows'] ?? null;

        return is_array($rows) ? array_values($rows) : [];
    }
}
