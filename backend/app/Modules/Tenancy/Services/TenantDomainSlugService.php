<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Models\TenantDomainEndpoint;
use Illuminate\Support\Str;

/**
 * Recommended hostname patterns for TowerCo tenants.
 *
 * Local:   {slug}.localhost
 * Test:    test.{slug}.{brand_domain}
 * Staging: staging.{slug}.{brand_domain}
 * App/Prod: app.{slug}.{brand_domain}  OR  {slug}.{brand_domain}
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
        $webPort = (int) (parse_url((string) env('FRONTEND_APP_URL', 'http://localhost:3001'), PHP_URL_PORT) ?: 3001);

        $endpoints = match (true) {
            app()->environment('local') && $environment === 'test' => [
                ['purpose' => 'test', 'hostname' => "test.{$slug}.localhost", 'is_primary' => true],
            ],
            app()->environment('local') && $environment === 'staging' => [
                ['purpose' => 'staging', 'hostname' => "staging.{$slug}.localhost", 'is_primary' => true],
            ],
            app()->environment('local') && $environment === 'production' => [
                ['purpose' => 'app', 'hostname' => "app.{$slug}.localhost", 'is_primary' => true],
            ],
            $environment === 'local' => [
                ['purpose' => 'local', 'hostname' => "{$slug}.localhost", 'is_primary' => true],
            ],
            $environment === 'test' => [
                ['purpose' => 'test', 'hostname' => "test.{$slug}.{$brandDomain}", 'is_primary' => true],
            ],
            $environment === 'staging' => [
                ['purpose' => 'staging', 'hostname' => "staging.{$slug}.{$brandDomain}", 'is_primary' => true],
            ],
            default => [
                ['purpose' => 'app', 'hostname' => "app.{$slug}.{$brandDomain}", 'is_primary' => true],
                ['purpose' => 'root', 'hostname' => "{$slug}.{$brandDomain}", 'is_primary' => false],
            ],
        };

        foreach ($endpoints as &$endpoint) {
            $endpoint['login_url'] = $this->loginUrl($endpoint['hostname'], $environment, $webPort);
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

    private function deriveSlugFromDomain(string $domain): string
    {
        $host = strtolower(trim($domain));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $parts = explode('.', $host);

        return $this->normalizeSlug($parts[0] ?? 'tenant');
    }

    private function loginUrl(string $hostname, string $environment, int $webPort): string
    {
        if ($environment === 'local' || str_ends_with($hostname, '.localhost')) {
            return "http://{$hostname}:{$webPort}/login";
        }

        return "https://{$hostname}/login";
    }
}
