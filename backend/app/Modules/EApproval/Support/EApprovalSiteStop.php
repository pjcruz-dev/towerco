<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * One forecast column holds the site ID and the location that belongs to it.
 */
final class EApprovalSiteStop
{
    /**
     * @return array{site: string, location: string}
     */
    public static function parse(string $raw): array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && (array_key_exists('site', $decoded) || array_key_exists('location', $decoded))) {
                return [
                    'site' => trim((string) ($decoded['site'] ?? '')),
                    'location' => trim((string) ($decoded['location'] ?? '')),
                ];
            }
        }

        return ['site' => $trimmed, 'location' => ''];
    }

    public static function pack(string $site, string $location): string
    {
        $site = trim($site);
        $location = trim($location);
        if ($site === '') {
            return '';
        }
        if ($location === '') {
            return $site;
        }

        return json_encode(['site' => $site, 'location' => $location], JSON_THROW_ON_ERROR);
    }

    public static function display(string $raw): string
    {
        $parsed = self::parse($raw);
        if ($parsed['site'] === '' || ! str_starts_with(trim($raw), '{')) {
            return trim($raw);
        }
        if ($parsed['location'] === '') {
            return $parsed['site'];
        }

        return $parsed['site'].' — '.$parsed['location'];
    }
}
