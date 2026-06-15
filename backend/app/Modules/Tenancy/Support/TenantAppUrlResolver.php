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
        $parts = parse_url($configured) ?: [];
        $scheme = $parts['scheme'] ?? 'http';
        $explicitPort = FrontendDevUrl::explicitPort();
        $port = $explicitPort !== null ? ':'.$explicitPort : '';

        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $domain = Domain::query()->where('tenant_id', $tenant->id)->orderBy('id')->value('domain');
            if (is_string($domain) && $domain !== '') {
                return "{$scheme}://{$domain}{$port}{$normalizedPath}";
            }
        }

        return $configured.$normalizedPath;
    }

    /** Tenant slug for mail subjects/headers (e.g. ATC), not the platform product name. */
    public function mailBrandLabel(): string
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $slug = trim((string) ($tenant->slug ?? ''));
            if ($slug !== '') {
                return strtoupper($slug);
            }
        }

        return (string) config('app.name', 'TowerOS');
    }

    public function subjectPrefix(): string
    {
        return '['.$this->mailBrandLabel().']';
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
