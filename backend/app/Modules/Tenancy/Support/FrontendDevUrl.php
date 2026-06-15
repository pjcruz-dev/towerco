<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * Resolves local frontend base URL and optional dev port from FRONTEND_APP_URL / toweros.tenant_app_url.
 *
 * Default local stack: http://localhost (port 80) — platform at /platform, tenants at {slug}.localhost.
 */
final class FrontendDevUrl
{
    public const DEFAULT_BASE = 'http://localhost';

    public static function configuredBaseUrl(): string
    {
        foreach ([config('toweros.tenant_app_url'), env('FRONTEND_APP_URL')] as $url) {
            if (is_string($url) && trim($url) !== '') {
                return rtrim(trim($url), '/');
            }
        }

        return self::DEFAULT_BASE;
    }

    /**
     * Non-standard HTTP(S) port from config, or null when using default 80/443 (omit from URLs).
     */
    public static function explicitPort(): ?int
    {
        $port = parse_url(self::configuredBaseUrl(), PHP_URL_PORT);
        if (! is_int($port) || $port <= 0) {
            return null;
        }

        if (in_array($port, [80, 443], true)) {
            return null;
        }

        return $port;
    }

    public static function authority(string $hostname): string
    {
        $port = self::explicitPort();

        return $port !== null ? "{$hostname}:{$port}" : $hostname;
    }

    public static function tenantLoginUrl(string $hostname, string $environment = 'local'): string
    {
        if ($environment === 'local' || str_ends_with($hostname, '.localhost')) {
            return 'http://'.self::authority($hostname).'/login';
        }

        return "https://{$hostname}/login";
    }

    public static function withPortSuffix(string $hostname): string
    {
        return self::authority($hostname);
    }
}
