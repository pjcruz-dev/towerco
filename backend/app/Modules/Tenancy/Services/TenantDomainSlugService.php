<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Support\FrontendDevUrl;
use App\Models\Tenant;
use App\Models\TenantDomainEndpoint;
use Illuminate\Support\Str;

/**
 * Recommended hostname patterns for TowerCo tenants.
 *
 * When the platform frontend is on localhost (developer laptop):
 *   Local:   {slug}.localhost
 *   Test:    test.{slug}.localhost
 *   Staging: staging.{slug}.localhost
 *   App/Prod: app.{slug}.localhost
 *
 * When the platform frontend is on LAN/IP/public DNS (brand-owned hosts):
 *   Local:   local.{brand_domain}
 *   Test:    test.{brand_domain}
 *   Staging: staging.{brand_domain}
 *   App/Prod: app.{brand_domain}  (+ optional apex {brand_domain})
 *
 * Slug remains the org identity for linking staging ↔ production (environment switch).
 * It is intentionally omitted from brand DNS so production can be app.alliancetowers.com.
 */
final class TenantDomainSlugService
{
    /**
     * @return array{
     *   slug: string,
     *   brand_domain: string,
     *   environment: string,
     *   endpoints: list<array{purpose: string, hostname: string, is_primary: bool, login_url: string}>
     * }
     */
    public function recommend(Tenant $tenant, ?string $slug = null, ?string $brandDomain = null, string $environment = 'production'): array
    {
        $slug = $this->normalizeSlug($slug ?? (string) ($tenant->slug ?? ''));
        if ($slug === '') {
            $slug = $this->deriveSlugFromDomain($tenant->domains()->first()?->domain ?? 'tenant');
        }

        $brandDomain = $this->normalizeBrandDomain($brandDomain ?? (string) ($tenant->brand_domain ?? 'toweros.app'));
        $useLocalhostHosts = $this->useLocalhostStyleHosts();
        // Creating staging/production from a laptop console should still recommend brand DNS
        // (app.alliancetowers.com), not app.{slug}.localhost.
        if ($useLocalhostHosts && $environment !== 'local' && $this->looksLikePublicBrandDomain($brandDomain)) {
            $useLocalhostHosts = false;
        }
        $endpoints = match (true) {
            $useLocalhostHosts && $environment === 'test' => [
                ['purpose' => 'test', 'hostname' => "test.{$slug}.localhost", 'is_primary' => true],
            ],
            $useLocalhostHosts && $environment === 'staging' => [
                ['purpose' => 'staging', 'hostname' => "staging.{$slug}.localhost", 'is_primary' => true],
            ],
            $useLocalhostHosts && $environment === 'production' => [
                ['purpose' => 'app', 'hostname' => "app.{$slug}.localhost", 'is_primary' => true],
            ],
            $useLocalhostHosts && $environment === 'local' => [
                ['purpose' => 'local', 'hostname' => "{$slug}.localhost", 'is_primary' => true],
            ],
            $environment === 'local' => [
                ['purpose' => 'local', 'hostname' => "local.{$brandDomain}", 'is_primary' => true],
            ],
            $environment === 'test' => [
                ['purpose' => 'test', 'hostname' => "test.{$brandDomain}", 'is_primary' => true],
            ],
            $environment === 'staging' => [
                ['purpose' => 'staging', 'hostname' => "staging.{$brandDomain}", 'is_primary' => true],
            ],
            default => [
                ['purpose' => 'app', 'hostname' => "app.{$brandDomain}", 'is_primary' => true],
                ['purpose' => 'root', 'hostname' => $brandDomain, 'is_primary' => false],
            ],
        };

        foreach ($endpoints as &$endpoint) {
            $endpoint['login_url'] = FrontendDevUrl::tenantLoginUrl($endpoint['hostname'], $environment);
        }
        unset($endpoint);

        return [
            'slug' => $slug,
            'brand_domain' => $brandDomain,
            'environment' => $environment,
            'endpoints' => $endpoints,
        ];
    }

    public function persistEndpoints(Tenant $tenant, array $recommendation): void
    {
        foreach ($recommendation['endpoints'] as $endpoint) {
            TenantDomainEndpoint::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'purpose' => $endpoint['purpose']],
                [
                    'hostname' => $endpoint['hostname'],
                    'is_primary' => (bool) $endpoint['is_primary'],
                ],
            );
        }
    }

    public function normalizeSlug(string $value): string
    {
        $slug = Str::of($value)->lower()->replace(['_', ' '], '-')->trim('-')->toString();
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';

        return substr($slug, 0, 32);
    }

    private function normalizeBrandDomain(string $value): string
    {
        $domain = strtolower(trim($value));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;

        return trim($domain, '/');
    }

    /**
     * Prefer *.localhost hostnames only when the configured tenant/frontend base is a local browser host.
     * LAN deploys (http://192.168.90.24, http://*.toweros.lan) must use brand_domain even if APP_ENV=local.
     */
    private function useLocalhostStyleHosts(): bool
    {
        $base = (string) (
            config('toweros.tenant_app_url')
            ?: config('app.url')
            ?: ''
        );
        $host = strtolower((string) (parse_url($base, PHP_URL_HOST) ?: ''));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.localhost');
    }

    private function looksLikePublicBrandDomain(string $brandDomain): bool
    {
        $brand = strtolower(trim($brandDomain));
        if ($brand === '' || ! str_contains($brand, '.')) {
            return false;
        }

        return ! str_ends_with($brand, '.localhost');
    }

    private function deriveSlugFromDomain(string $domain): string
    {
        $host = strtolower(trim($domain));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $parts = explode('.', $host);

        return $this->normalizeSlug($parts[0] ?? 'tenant');
    }

}
