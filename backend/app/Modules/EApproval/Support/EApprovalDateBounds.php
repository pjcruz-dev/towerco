<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Designer date floors: no past dates, a lead of N calendar days, or on/after another field.
 */
final class EApprovalDateBounds
{
    /**
     * @param  array<string, mixed>|null  $validation
     * @param  array<string, mixed>  $values
     */
    public static function valueError(?array $validation, string $value, array $values, string $label, ?DateTimeInterface $today = null): ?string
    {
        $raw = trim($value);
        if ($raw === '' || $validation === null) {
            return null;
        }

        $min = self::earliest($validation, $values, $today);
        if ($min === null) {
            return null;
        }

        foreach (self::datesIn($raw) as $date) {
            if ($date < $min) {
                return self::message($validation, $label);
            }
        }

        return null;
    }

    /**
     * First broken date or subsidiary rule on a grid. One message, so a line does not stack the same error.
     *
     * @param  array<string, mixed>|null  $options
     * @param  array<string, mixed>  $values
     */
    public static function gridError(?array $options, mixed $raw, array $values, ?DateTimeInterface $today = null): ?string
    {
        $columns = self::columnRecords($options);
        if ($columns === []) {
            return null;
        }

        foreach (self::rows($raw) as $row) {
            if (! is_array($row) || ! self::rowHasContent($row)) {
                continue;
            }
            foreach ($columns as $index => $column) {
                if (($column['hidden'] ?? false) === true) {
                    continue;
                }
                $cell = trim((string) ($row[(string) $index] ?? $row[$index] ?? ''));
                if ($cell === '') {
                    continue;
                }
                $label = trim((string) ($column['label'] ?? 'Date'));
                $dateError = self::valueError($column, $cell, $values, $label !== '' ? $label : 'Date', $today);
                if ($dateError !== null) {
                    return $dateError;
                }
                $siteError = self::subsidiaryError($column, $cell, $values, $label !== '' ? $label : 'Site');
                if ($siteError !== null) {
                    return $siteError;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $values
     */
    private static function subsidiaryError(array $column, string $cell, array $values, string $label): ?string
    {
        $field = trim((string) ($column['subsidiary_field'] ?? ''));
        if ($field === '') {
            return null;
        }
        $expected = strtoupper(trim((string) ($values[$field] ?? '')));
        if ($expected === '') {
            return null;
        }
        foreach (self::siteSubsidiaries($cell) as $subsidiary) {
            if (strtoupper($subsidiary) !== $expected) {
                return __(':label must belong to :subsidiary.', [
                    'label' => $label,
                    'subsidiary' => trim((string) ($values[$field] ?? '')),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $values
     */
    private static function earliest(array $rule, array $values, ?DateTimeInterface $today): ?DateTimeImmutable
    {
        $kind = (string) ($rule['date_min'] ?? '');
        $start = $today !== null
            ? DateTimeImmutable::createFromInterface($today)->setTime(0, 0)
            : new DateTimeImmutable('today');

        if ($kind === 'today') {
            return $start;
        }
        if ($kind === 'lead') {
            $days = is_numeric($rule['lead_days'] ?? null) ? max(1, (int) $rule['lead_days']) : 3;

            return $start->modify('+'.$days.' days');
        }
        if ($kind === 'field') {
            $name = trim((string) ($rule['date_min_field'] ?? ''));
            $raw = substr(trim((string) ($values[$name] ?? '')), 0, 10);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

            return $date === false ? null : $date;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private static function message(array $rule, string $label): string
    {
        $kind = (string) ($rule['date_min'] ?? '');
        if ($kind === 'lead') {
            $days = is_numeric($rule['lead_days'] ?? null) ? max(1, (int) $rule['lead_days']) : 3;

            return __(':label must be at least :days days from today.', [
                'label' => $label,
                'days' => $days,
            ]);
        }
        if ($kind === 'today') {
            return __(':label cannot be in the past.', ['label' => $label]);
        }

        return __(':label must be on or after the linked date.', ['label' => $label]);
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private static function datesIn(string $raw): array
    {
        $dates = [];
        if (str_starts_with($raw, '{')) {
            try {
                $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $parsed = null;
            }
            if (is_array($parsed)) {
                foreach (['from', 'to'] as $key) {
                    $day = substr(trim((string) ($parsed[$key] ?? '')), 0, 10);
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
                    if ($date !== false) {
                        $dates[] = $date;
                    }
                }
            }
        }
        if ($dates !== []) {
            return $dates;
        }
        $day = substr($raw, 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return $date === false ? [] : [$date];
    }

    /**
     * @return list<string>
     */
    private static function siteSubsidiaries(string $cell): array
    {
        if (! str_starts_with($cell, '{')) {
            return [];
        }
        try {
            $parsed = json_decode($cell, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (! is_array($parsed)) {
            return [];
        }
        $entries = isset($parsed['sites']) && is_array($parsed['sites']) ? $parsed['sites'] : [$parsed];
        $found = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $fields = $entry['fields'] ?? null;
            if (! is_array($fields)) {
                continue;
            }
            $subsidiary = trim((string) ($fields['subsidiary'] ?? ''));
            if ($subsidiary !== '') {
                $found[] = $subsidiary;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return list<array<string, mixed>>
     */
    private static function columnRecords(?array $options): array
    {
        $columns = is_array($options) ? ($options['columns'] ?? null) : null;
        if (! is_array($columns)) {
            return [];
        }
        $records = [];
        foreach ($columns as $column) {
            if (is_array($column)) {
                $records[] = $column;
            }
        }

        return $records;
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
        $rows = $decoded['rows'] ?? null;

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @param  array<mixed>  $row
     */
    private static function rowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }
}
