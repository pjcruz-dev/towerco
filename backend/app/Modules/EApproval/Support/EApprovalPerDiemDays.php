<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalFormField;

/**
 * Lodging uses the dates between the first day and the last day.
 * Per diem counts every day on that line, including the first day and the last day.
 */
final class EApprovalPerDiemDays
{
    /**
     * @param  list<array{from: string, to: string}>  $ranges
     */
    public static function betweenTrips(array $ranges): ?int
    {
        $outbound = null;
        $inbound = null;
        foreach ($ranges as $range) {
            $from = self::calendarDate(trim((string) ($range['from'] ?? '')));
            $to = self::calendarDate(trim((string) ($range['to'] ?? '')));
            if (! self::isIsoDate($from) || ! self::isIsoDate($to) || $from > $to) {
                continue;
            }
            if ($outbound === null || $from < $outbound) {
                $outbound = $from;
            }
            if ($inbound === null || $to > $inbound) {
                $inbound = $to;
            }
        }

        if ($outbound === null || $inbound === null) {
            return null;
        }

        return self::daysStrictlyBetween($outbound, $inbound);
    }

    /**
     * Dates live on the forecast line. Older forms still keep them on the activity grid.
     *
     * @param  iterable<mixed>  $fields
     * @param  array<string, mixed>  $values
     */
    public static function fromFormFields(iterable $fields, array $values): ?int
    {
        $grids = [];
        foreach ($fields as $field) {
            if (! $field instanceof EApprovalFormField) {
                continue;
            }
            $name = (string) $field->name;
            if ($name === 'expense_lines' || $name === 'activity_lines') {
                $grids[$name] = $field;
            }
        }

        foreach (['expense_lines', 'activity_lines'] as $name) {
            $field = $grids[$name] ?? null;
            if (! $field instanceof EApprovalFormField) {
                continue;
            }
            $labels = EApprovalFieldOptionsParser::gridColumns(is_array($field->options) ? $field->options : null);
            $dateIndex = self::dateLabelIndex($labels);
            if ($dateIndex === null) {
                continue;
            }

            $raw = $values[$name] ?? '';
            $gridRaw = is_scalar($raw) ? trim((string) $raw) : '';
            if ($gridRaw === '') {
                return null;
            }

            try {
                $decoded = json_decode($gridRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }
            $rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];

            return self::fromActivityRows(is_array($rows) ? $rows : [], $dateIndex);
        }

        return null;
    }

    /**
     * @param  list<mixed>  $rows
     */
    public static function fromActivityRows(array $rows, int $dateIndex): ?int
    {
        $ranges = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $raw = trim((string) ($row[(string) $dateIndex] ?? $row[$dateIndex] ?? ''));
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                continue;
            }
            $ranges[] = [
                'from' => trim((string) ($decoded['from'] ?? '')),
                'to' => trim((string) ($decoded['to'] ?? '')),
            ];
        }

        return self::betweenTrips($ranges);
    }

    /**
     * Inclusive day count for one per diem line. The same start and end is one day.
     */
    public static function inclusiveDays(string $from, string $to): ?int
    {
        $from = self::calendarDate($from);
        $to = self::calendarDate($to);
        if (! self::isIsoDate($from) || ! self::isIsoDate($to) || $from > $to) {
            return null;
        }

        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if ($start === false || $end === false) {
            return null;
        }

        return (int) $start->diff($end)->days + 1;
    }

    /**
     * Nights from the first date to the last date. 14 Oct through 16 Oct is 2. One date is 1.
     */
    public static function lodgingNights(string $from, string $to): ?int
    {
        $from = self::calendarDate($from);
        $to = self::calendarDate($to);
        if (! self::isIsoDate($from) || ! self::isIsoDate($to) || $from > $to) {
            return null;
        }

        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if ($start === false || $end === false) {
            return null;
        }

        return max((int) $start->diff($end)->days, 1);
    }

    public static function lodgingNightsForDateValue(string $raw): ?int
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return null;
        }

        return self::lodgingNights(
            trim((string) ($decoded['from'] ?? '')),
            trim((string) ($decoded['to'] ?? '')),
        );
    }

    /**
     * @param  array<mixed>  $row
     */
    public static function lodgingNightsForRow(array $row, ?int $dateIndex): ?int
    {
        if ($dateIndex === null) {
            return null;
        }

        return self::lodgingNightsForDateValue((string) ($row[(string) $dateIndex] ?? $row[$dateIndex] ?? ''));
    }

    public static function forDateValue(string $raw): ?int
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return null;
        }

        return self::inclusiveDays(
            trim((string) ($decoded['from'] ?? '')),
            trim((string) ($decoded['to'] ?? '')),
        );
    }

    /**
     * @param  array<mixed>  $row
     */
    public static function forRow(array $row, ?int $dateIndex): ?int
    {
        if ($dateIndex === null) {
            return null;
        }

        return self::forDateValue((string) ($row[(string) $dateIndex] ?? $row[$dateIndex] ?? ''));
    }

    /**
     * @param  list<string>  $labels
     */
    public static function dateLabelIndex(array $labels): ?int
    {
        foreach ($labels as $index => $label) {
            $normalized = strtolower(trim((string) $label));
            if ($normalized === 'date' || str_starts_with($normalized, 'from date')) {
                return (int) $index;
            }
        }

        return null;
    }

    public static function lineTotal(float $dailyRate, ?int $days): float
    {
        if ($dailyRate <= 0 || $days === null || $days < 1) {
            return 0.0;
        }

        return round($dailyRate * $days, 2);
    }

    public static function daysStrictlyBetween(string $after, string $before): int
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', self::calendarDate($after));
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', self::calendarDate($before));
        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }

        return max(0, (int) $start->diff($end)->days - 1);
    }

    private static function isIsoDate(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    /** Calendar day from `Y-m-d` or `Y-m-dTH:i`. The clock does not change the day count. */
    private static function calendarDate(string $value): string
    {
        $trimmed = trim($value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
