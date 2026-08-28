<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Builds tenant-scoped frontend URLs (login, deep links).
 */
final class TenantAppUrlResolver
{
    public function urlForCurrentTenant(string $path): string
    {
        $normalizedPath = str_starts_with($path, '/') ? $path : "/{$path}";
        $configured = FrontendDevUrl::configuredBaseUrl();
        $explicitPort = FrontendDevUrl::explicitPort();
        $port = $explicitPort !== null ? ':'.$explicitPort : '';
        $environment = function_exists('app') ? (string) app()->environment() : 'production';

        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $domain = Domain::query()->where('tenant_id', $tenant->id)->orderBy('id')->value('domain');
            if (is_string($domain) && $domain !== '') {
                // Prefer request host when it matches a tenant domain (sync exports on app/staging).
                $requestHost = function_exists('request') ? request()?->getHost() : null;
                if (is_string($requestHost) && $requestHost !== '') {
                    $matched = Domain::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('domain', strtolower($requestHost))
                        ->exists();
                    if ($matched) {
                        $domain = strtolower($requestHost);
                    }
                }

                $scheme = FrontendDevUrl::schemeForTenantHost($domain, $environment);

                return "{$scheme}://{$domain}{$port}{$normalizedPath}";
            }
        }

        return $configured.$normalizedPath;
    }

    /** Tenant slug/domain for mail subjects/headers (e.g. ATC), not the platform product name. */
    public function mailBrandLabel(): string
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $slug = trim((string) ($tenant->slug ?? ''));
            if ($slug !== '') {
                return strtoupper($slug);
            }

            $domain = Domain::query()->where('tenant_id', $tenant->id)->orderBy('id')->value('domain');
            if (is_string($domain) && $domain !== '') {
                $hostLabel = $this->hostLabelFromDomain($domain);
                if ($hostLabel !== null) {
                    return $hostLabel;
                }
            }
        }

        return (string) config('app.name', 'TowerOS');
    }

    public function subjectPrefix(): string
    {
        return '['.$this->mailBrandLabel().']';
    }

    /** First hostname label suitable for branding (e.g. atc.localhost → ATC). */
    private function hostLabelFromDomain(string $domain): ?string
    {
        $host = strtolower(trim($domain));
        if ($host === '' || $host === 'localhost') {
            return null;
        }

        $label = explode('.', $host)[0] ?? '';
        $label = trim($label);
        if ($label === '' || $label === 'www' || $label === 'localhost') {
            return null;
        }

        return strtoupper($label);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runForTenant(?string $tenantId, callable $callback): mixed
    {
        $initializedHere = false;

        if (is_string($tenantId) && $tenantId !== '') {
            $current = tenant();
            $currentId = $current instanceof Tenant ? (string) $current->getTenantKey() : null;

            if ($currentId !== $tenantId) {
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant instanceof Tenant) {
                    tenancy()->initialize($tenant);
                    $initializedHere = true;
                }
            }
        }

        try {
            return $callback();
        } finally {
            if ($initializedHere) {
                tenancy()->end();
            }
        }
    }
}
