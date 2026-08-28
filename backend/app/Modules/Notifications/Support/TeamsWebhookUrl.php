<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * Normalize / validate Microsoft Teams Workflows (Power Automate) webhook URLs.
 */
final class TeamsWebhookUrl
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        // Teams "Copy webhook link" often includes an explicit :443; strip for cleaner storage.
        $url = preg_replace('#^(https://[^/\s]+):443(/)#i', '$1$2', $url) ?? $url;

        return $url;
    }

    public static function isValid(string $url): bool
    {
        $url = self::normalize($url);
        if ($url === '') {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https' || $scheme === 'http';
    }
}
