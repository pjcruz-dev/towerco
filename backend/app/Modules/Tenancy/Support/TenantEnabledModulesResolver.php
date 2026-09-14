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
        'dynamic_entities',
        'e_approval',
        'ticketing',
        'billings',
        'ai_assistant',
        'doc_extract',
    ];

    /** @var array<string, string> */
    public const MODULE_LABELS = [
        'core' => 'Dashboard',
        'team_access' => 'Team & Access',
        'e_approval' => 'E-Forms',
        'dynamic_entities' => 'Dynamic Entities',
        'ticketing' => 'Ticketing',
        'billings' => 'Billings',
        'ai_assistant' => 'AI Assistant',
        'doc_extract' => 'DocExtract',
    ];

    /** @var array<string, string> */
    public const MODULE_DESCRIPTIONS = [
        'dynamic_entities' => 'Dynamic entity packs for ATC PM, Finance, and Ticketing (Manage Fields + records).',
        'billings' => 'Tenant subscription, usage, and self-serve plan billing (/billing).',
        'ai_assistant' => 'In-app help assistant for workflows, permissions, and how-to guidance.',
        'doc_extract' => 'Upload finance PDFs, OCR scan, map fields, review, and export CSV/XLSX.',
    ];

    /**
     * @return list<string>
     */
    public function platformModules(): array
    {
        $configured = config('toweros.tenant_modules.enabled');
        if (! is_array($configured) || $configured === []) {
            return $this->normalizeSelection([
                'dynamic_entities',
                'e_approval',
                'ticketing',
                'billings',
                'ai_assistant',
                'doc_extract',
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
     *   labels: array<string, string>,
     *   descriptions: array<string, string>
     * }
     */
    public function catalogForPlatformApi(): array
    {
        return [
            'platform_modules' => $this->platformModules(),
            'toggleable_modules' => $this->toggleableModules(),
            'required_modules' => self::REQUIRED_MODULES,
            'labels' => self::MODULE_LABELS,
            'descriptions' => self::MODULE_DESCRIPTIONS,
        ];
    }
}
