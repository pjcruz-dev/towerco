<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

/**
 * Builds explicit browser Origin allowlists for Laravel CORS middleware.
 *
 * Origins are derived from configured frontend URLs and tenant hostnames registered
 * in the central database (same source as Sanctum stateful domains).
 */
final class CorsAllowedOriginResolver
{
    public const CACHE_KEY = 'toweros:cors:allowed-origins';

    public function __construct(
        private readonly SanctumStatefulDomainResolver $statefulDomains,
    ) {}

    /**
     * @return list<string>
     */
    public function resolve(): array
    {
        $ttl = (int) config('toweros.cors.allowed_origin_cache_ttl', 3600);

        if ($ttl <= 0) {
            return $this->build();
        }

        return Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->build());
    }

    /**
     * @return list<string>
     */
    public function resolvePatterns(): array
    {
        $patterns = config('toweros.cors.allowed_origin_patterns', []);
        if (is_string($patterns)) {
            $patterns = array_filter(array_map('trim', explode(',', $patterns)));
        }

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (mixed $pattern): string => $this->normalizeOriginPattern((string) $pattern),
            $patterns,
        )));
    }

    /**
     * fruitcake/php-cors expects delimiter-wrapped regex; env defaults use wildcard hosts.
     */
    private function normalizeOriginPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return $pattern;
        }

        if (preg_match('/^[#\/|@%~].*[#\/|@%~]$/', $pattern) === 1) {
            return $pattern;
        }

        $regex = preg_quote($pattern, '#');
        $regex = str_replace('\*', '.*', $regex);

        return '#^'.$regex.'$#i';
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    private function build(): array
    {
        $origins = collect();

        foreach ($this->configuredBaseUrls() as $url) {
            $origin = $this->normalizeOrigin($url);
            if ($origin !== null) {
                $origins->push($origin);
            }
        }

        foreach ($this->statefulDomains->resolve() as $host) {
            if ($host === Sanctum::$currentRequestHostPlaceholder) {
                continue;
            }

            $origin = $this->hostToOrigin((string) $host);
            if ($origin !== null) {
                $origins->push($origin);
            }
        }

        foreach ($this->extras() as $extra) {
            $origin = $this->normalizeOrigin($extra);
            if ($origin !== null) {
                $origins->push($origin);
            }
        }

        return $origins
            ->map(fn (string $origin): string => rtrim($origin, '/'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function configuredBaseUrls(): array
    {
        $urls = [
            config('toweros.tenant_app_url'),
            env('FRONTEND_APP_URL'),
            config('app.url'),
        ];

        return array_values(array_filter(
            array_map(static fn (mixed $url): string => is_string($url) ? trim($url) : '', $urls),
            static fn (string $url): bool => $url !== '',
        ));
    }

    /**
     * @return list<string>
     */
    private function extras(): array
    {
        $extras = config('toweros.cors.allowed_origin_extras', []);
        if (is_string($extras)) {
            $extras = array_filter(array_map('trim', explode(',', $extras)));
        }

        return is_array($extras) ? $extras : [];
    }

    private function normalizeOrigin(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            return $this->hostToOrigin($value);
        }

        $parts = parse_url($value);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : null;
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : null;
        if ($scheme === null || $host === null || $host === '') {
            return null;
        }

        $port = isset($parts['port']) && is_int($parts['port']) && $parts['port'] > 0
            ? $parts['port']
            : null;

        if ($port !== null && ! $this->shouldIncludePort($scheme, $port)) {
            $port = null;
        }

        $authority = $port !== null ? "{$host}:{$port}" : $host;

        if ($host === '::1') {
            $authority = '[::1]'.($port !== null ? ":{$port}" : '');
        }

        return "{$scheme}://{$authority}";
    }

    private function hostToOrigin(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        $scheme = $this->schemeForHost($host);

        if ($host === '::1') {
            return "{$scheme}://[::1]";
        }

        return "{$scheme}://{$host}";
    }

    private function schemeForHost(string $host): string
    {
        $hostname = $host;
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $hostname = explode(':', $host, 2)[0];
        }

        if (
            app()->environment('local')
            || str_ends_with($hostname, '.localhost')
            || in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)
        ) {
            return 'http';
        }

        return 'https';
    }

    private function shouldIncludePort(string $scheme, int $port): bool
    {
        return ! (
            ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443)
        );
    }
}
