<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\Tenant;

final class TenantEnabledModulesResolver
{
    /** @var list<string> */
    public const REQUIRED_MODULES = ['core', 'team_access'];

    /** @var list<string> */
    public const TOGGLEABLE_MODULES = [
        'project_one',
        'e_approval',
        'sites',
        'gis',
        'tower_one',
        'fiber_one',
        'asset_one',
        'ticketing',
        'procurement_one',
        'finance_one',
        'documents',
    ];

    /** @var array<string, string> */
    public const MODULE_LABELS = [
        'core' => 'Dashboard',
        'team_access' => 'Team & Access',
        'project_one' => 'Project-One',
        'e_approval' => 'E-Approval',
        'gis' => 'GIS',
        'sites' => 'Sites',
        'tower_one' => 'Tower-One',
        'fiber_one' => 'Fiber-One',
        'asset_one' => 'Asset-One',
        'ticketing' => 'Ticketing',
        'procurement_one' => 'Procurement-One',
        'finance_one' => 'Finance-One',
        'documents' => 'Documents',
    ];

    /**
     * @return list<string>
     */
    public function platformModules(): array
    {
        $configured = config('toweros.tenant_modules.enabled');
        if (! is_array($configured) || $configured === []) {
            return $this->normalizeSelection([
                'project_one',
                'e_approval',
                'ticketing',
                'procurement_one',
                'sites',
                'documents',
            ]);
        }

        return $this->normalizeSelection(array_map('strval', $configured));
    }

    /**
     * @return list<string>
     */
    public function toggleableModules(): array
    {
        return array_values(array_intersect(self::TOGGLEABLE_MODULES, $this->platformModules()));
    }

    /**
     * @return list<string>
     */
    public function resolveForTenant(?Tenant $tenant): array
    {
        $platform = $this->platformModules();

        if (! $tenant instanceof Tenant) {
            return $platform;
        }

        $override = $tenant->enabled_modules;
        if (! is_array($override) || $override === []) {
            return $platform;
        }

        $selected = array_values(array_intersect(
            $this->normalizeSelection(array_map('strval', $override)),
            $platform,
        ));

        return $selected;
    }

    /**
     * @return list<string>
     */
    public function resolveForCurrentTenant(): array
    {
        $tenant = tenant();

        return $this->resolveForTenant($tenant instanceof Tenant ? $tenant : null);
    }

    /**
     * @param  list<string>  $selectedToggleable
     * @return list<string>
     */
    public function normalizeSelection(array $selectedToggleable): array
    {
        $toggleable = array_values(array_intersect(
            self::TOGGLEABLE_MODULES,
            $selectedToggleable,
        ));

        return array_values(array_unique(array_merge(self::REQUIRED_MODULES, $toggleable)));
    }

    /**
     * @return array{
     *   platform_modules: list<string>,
     *   toggleable_modules: list<string>,
     *   required_modules: list<string>,
     *   labels: array<string, string>
     * }
     */
    public function catalogForPlatformApi(): array
    {
        return [
            'platform_modules' => $this->platformModules(),
            'toggleable_modules' => $this->toggleableModules(),
            'required_modules' => self::REQUIRED_MODULES,
            'labels' => self::MODULE_LABELS,
        ];
    }
}
