<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Listeners;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Spatie Permission caches the permission registry in Redis under a single global key.
 * In multi-tenant mode that poisons {@see Authorizable::can()} checks after the first
 * tenant (or central context) loads the cache. Scope the key per tenant on bootstrap.
 */
final class ScopeTenantPermissionCache
{
    public const BASE_CACHE_KEY = 'spatie.permission.cache';

    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function handleTenancyInitialized(TenancyInitialized $event): void
    {
        $tenantId = $event->tenancy->tenant?->getTenantKey();
        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        $this->applyCacheKey(self::BASE_CACHE_KEY.'.tenant.'.$tenantId);
    }

    public function handleTenancyEnded(TenancyEnded $event): void
    {
        $this->applyCacheKey(self::BASE_CACHE_KEY);
    }

    private function applyCacheKey(string $cacheKey): void
    {
        config(['permission.cache.key' => $cacheKey]);
        $this->registrar->initializeCache();
        $this->registrar->clearPermissionsCollection();
    }
}
