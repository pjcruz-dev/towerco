<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\TenantDomainEndpoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Builds Sanctum "stateful" SPA domains from central tenant records.
 *
 * TowerOS registers tenant hostnames in the database when environments are provisioned.
 * Sanctum validates Origin/Referer against this list for cookie/CSRF SPA auth.
 */
final class SanctumStatefulDomainResolver
{
    public const CACHE_KEY = 'toweros:sanctum:stateful-domains';

    /**
     * @return list<string>
     */
    public function resolve(): array
    {
        $ttl = (int) config('toweros.sanctum.stateful_domain_cache_ttl', 3600);

        if ($ttl <= 0) {
            return $this->build();
        }

        return Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->build());
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
        $domains = collect($this->baseDomains());

        if ($this->tablesExist()) {
            $connection = (string) config('tenancy.database.central_connection', config('database.default'));

            $domains = $domains
                ->merge(
                    Domain::on($connection)
                        ->pluck('domain')
                        ->all(),
                )
                ->merge(
                    TenantDomainEndpoint::on($connection)
                        ->pluck('hostname')
                        ->all(),
                );
        }

        $expanded = $domains
            ->flatMap(fn (mixed $host): array => $this->expandDevPortVariants((string) $host))
            ->map(fn (string $host): string => strtolower(trim($host)))
            ->filter(fn (string $host): bool => $host !== '')
            ->unique()
            ->values();

        $expanded->push(Sanctum::$currentRequestHostPlaceholder);

        return $expanded->all();
    }

    /**
     * @return list<string>
     */
    private function baseDomains(): array
    {
        $defaults = [
            'localhost',
            '127.0.0.1',
            '::1',
        ];

        foreach ([config('app.url'), config('toweros.tenant_app_url'), env('FRONTEND_APP_URL')] as $url) {
            $host = $this->hostFromUrl(is_string($url) ? $url : null);
            if ($host !== null) {
                $defaults[] = $host;
            }
        }

        $extras = config('toweros.sanctum.stateful_domain_extras', []);
        if (is_string($extras)) {
            $extras = array_filter(array_map('trim', explode(',', $extras)));
        }

        return array_values(array_unique(array_merge($defaults, is_array($extras) ? $extras : [])));
    }

    /**
     * @return list<string>
     */
    private function expandDevPortVariants(string $hostname): array
    {
        $hostname = strtolower(trim($hostname));
        if ($hostname === '') {
            return [];
        }

        if (str_contains($hostname, ':')) {
            return [$hostname];
        }

        $variants = [$hostname];

        if (! $this->shouldAppendDevPort($hostname)) {
            return $variants;
        }

        $withPort = FrontendDevUrl::withPortSuffix($hostname);
        if ($withPort !== $hostname) {
            $variants[] = $withPort;
        }

        return $variants;
    }

    private function shouldAppendDevPort(string $hostname): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        return $hostname === 'localhost'
            || str_ends_with($hostname, '.localhost');
    }

    private function hostFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);
        if (is_int($port) && $port > 0) {
            return strtolower("{$host}:{$port}");
        }

        return strtolower($host);
    }

    private function tablesExist(): bool
    {
        try {
            $connection = (string) config('tenancy.database.central_connection', config('database.default'));

            return Schema::connection($connection)->hasTable('domains')
                && Schema::connection($connection)->hasTable('tenant_domain_endpoints');
        } catch (\Throwable) {
            return false;
        }
    }
}
