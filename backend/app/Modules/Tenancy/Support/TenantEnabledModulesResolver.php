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
        'e_approval',
        'ticketing',
        'document_register',
        'doc_extract',
        'dynamic_entities',
        'ai_assistant',
    ];

    /** @var array<string, string> */
    public const MODULE_LABELS = [
        'core' => 'Dashboard',
        'team_access' => 'Team & Access',
        'e_approval' => 'E-Forms',
        'ticketing' => 'Ticketing',
        'document_register' => 'Document register',
        'doc_extract' => 'DocExtract',
        'dynamic_entities' => 'Dynamic Entities',
        'ai_assistant' => 'AI Assistant',
    ];

    /** @var array<string, string> */
    public const MODULE_DESCRIPTIONS = [
        'e_approval' => 'Forms, submissions, and approval workflows.',
        'ticketing' => 'Tickets, assignments, and SLA follow-up.',
        'document_register' => 'ISO master list of approved documents; start requests and revisions via E-Forms.',
        'doc_extract' => 'Upload finance PDFs, OCR scan, map fields, review, and export CSV/XLSX.',
        'dynamic_entities' => 'Dynamic entity packs, records, fields, reports, and workflows.',
        'ai_assistant' => 'In-app assistant for workflows, permissions, and how-to guidance.',
    ];

    /**
     * @return list<string>
     */
    public function platformModules(): array
    {
        $configured = config('toweros.tenant_modules.enabled');
        $fromConfig = is_array($configured) ? array_map('strval', $configured) : [];

        return $this->normalizeSelection(array_merge(self::TOGGLEABLE_MODULES, $fromConfig));
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
