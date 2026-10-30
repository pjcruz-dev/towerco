<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Plain text for one grid cell in workspace tables and line-item exports.
 */
final class EApprovalGridCellText
{
    public static function present(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_array($raw)) {
            $encoded = json_encode($raw);
            $raw = is_string($encoded) ? $encoded : '';
        } elseif (! is_scalar($raw)) {
            return '';
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }

        $range = self::dateRange($text);
        if ($range !== null) {
            return $range;
        }

        return EApprovalSiteStop::display($text);
    }

    private static function dateRange(string $text): ?string
    {
        if (! str_starts_with($text, '{')) {
            return null;
        }

        try {
            $parsed = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($parsed) || (! array_key_exists('from', $parsed) && ! array_key_exists('to', $parsed))) {
            return null;
        }

        $from = self::endpoint(trim((string) ($parsed['from'] ?? '')));
        $to = self::endpoint(trim((string) ($parsed['to'] ?? '')));
        if ($from === '' && $to === '') {
            return '';
        }
        if ($to === '' || $from === $to) {
            return $from !== '' ? $from : $to;
        }

        return $from.' – '.$to;
    }

    private static function endpoint(string $value): string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $value, $matches) === 1) {
            return $matches[1].' '.$matches[2];
        }

        return $value;
    }
}
