<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Microsoft-style tower licenses: each active Dynamic Entities tower site consumes one license.
 */
final class TenantTowerLicenseMeterService
{
    public const ENTITY_SLUG = 'tower_sites';

    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    public function billableCount(Tenant $tenant): int
    {
        if ($this->isCurrentTenant($tenant)) {
            return $this->countActiveTowerSites();
        }

        try {
            tenancy()->initialize($tenant);

            return $this->countActiveTowerSites();
        } catch (Throwable) {
            return 0;
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    public function licenseLimit(Tenant $tenant): int
    {
        return $this->entitlements->effectiveTowerLicenseLimit($tenant);
    }

    public function licensesAvailable(Tenant $tenant): int
    {
        return max(0, $this->licenseLimit($tenant) - $this->billableCount($tenant));
    }

    /**
     * @return array{used: int, limit: int, available: int}
     */
    public function snapshot(Tenant $tenant): array
    {
        $limit = $this->licenseLimit($tenant);
        $used = $this->billableCount($tenant);

        return [
            'used' => $used,
            'limit' => $limit,
            'available' => max(0, $limit - $used),
        ];
    }

    private function isCurrentTenant(Tenant $tenant): bool
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return false;
        }

        $current = tenancy()->tenant;

        return $current !== null && (string) $current->getTenantKey() === (string) $tenant->getTenantKey();
    }

    private function countActiveTowerSites(): int
    {
        if (! Schema::hasTable('dyn_entities') || ! Schema::hasTable('dyn_records')) {
            return 0;
        }

        $entityId = DynEntity::query()
            ->where('slug', self::ENTITY_SLUG)
            ->where('is_active', true)
            ->value('id');

        if ($entityId === null) {
            return 0;
        }

        return (int) DynRecord::query()
            ->where('entity_id', $entityId)
            ->where('is_deleted', false)
            ->count();
    }
}
