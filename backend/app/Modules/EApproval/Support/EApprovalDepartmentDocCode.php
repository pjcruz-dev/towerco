<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Converts long department labels into short document-number codes.
 * e.g. "Engineering & Design Department" → EDD, "Business Development" → BD.
 * Single-word / already-short values stay as sanitized codes (Finance → FINANCE, QMS → QMS).
 */
final class EApprovalDepartmentDocCode
{
    /** @var list<string> */
    private const STOP_WORDS = [
        'and', 'of', 'the', 'for', 'to', 'a', 'an', 'at', 'in', 'on', 'by', 'with',
    ];

    public static function fromLabel(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        // Already a short code (select value like EDD / QMS / HR).
        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $trimmed) ?? '');
        if ($compact !== '' && strlen($compact) <= 6 && ! preg_match('/\s/', $trimmed)) {
            return $compact;
        }

        $words = preg_split('/[^A-Za-z0-9]+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significant = [];
        foreach ($words as $word) {
            $lower = strtolower($word);
            if (in_array($lower, self::STOP_WORDS, true)) {
                continue;
            }
            $significant[] = $word;
        }

        if ($significant === []) {
            return $compact !== '' ? $compact : '';
        }

        if (count($significant) === 1) {
            $one = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $significant[0]) ?? '');

            return $one !== '' ? $one : $compact;
        }

        $initials = '';
        foreach ($significant as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '';
            $letter = strtoupper(substr($clean, 0, 1));
            if ($letter !== '') {
                $initials .= $letter;
            }
        }

        return $initials !== '' ? $initials : $compact;
    }
}
