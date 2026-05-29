<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\AdminOne\Models\AdminSettings;
use Illuminate\Support\Str;

class AdminSettingsService
{
    /**
     * @return array{kpi_config: array<string, mixed>|null, sla_config: array<string, mixed>|null, workflow_templates: list<array<string, mixed>>|null}
     */
    public function get(): array
    {
        $record = $this->singleton();

        return [
            'kpi_config' => $record->kpi_config,
            'sla_config' => $record->sla_config,
            'workflow_templates' => $record->workflow_templates,
        ];
    }

    /**
     * @param  array{kpi_config?: array<string, mixed>|null, sla_config?: array<string, mixed>|null, workflow_templates?: list<array<string, mixed>>|null}  $payload
     */
    public function update(array $payload): array
    {
        $record = $this->singleton();

        if (array_key_exists('kpi_config', $payload)) {
            $record->kpi_config = $payload['kpi_config'];
        }
        if (array_key_exists('sla_config', $payload)) {
            $record->sla_config = $payload['sla_config'];
        }
        if (array_key_exists('workflow_templates', $payload)) {
            $record->workflow_templates = $payload['workflow_templates'];
        }

        $record->save();

        return $this->get();
    }

    private function singleton(): AdminSettings
    {
        $existing = AdminSettings::query()->first();
        if ($existing) {
            return $existing;
        }

        return AdminSettings::query()->create([
            'id' => (string) Str::uuid(),
            'kpi_config' => [
                'targets' => [
                    ['key' => 'site_uptime_pct', 'label' => 'Site uptime', 'target' => 99.5, 'unit' => '%'],
                    ['key' => 'wo_sla_pct', 'label' => 'Work order SLA', 'target' => 95, 'unit' => '%'],
                ],
            ],
            'sla_config' => [
                'policies' => [
                    ['severity' => 'critical', 'response_minutes' => 15, 'resolve_hours' => 4],
                    ['severity' => 'high', 'response_minutes' => 60, 'resolve_hours' => 24],
                ],
            ],
            'workflow_templates' => [],
        ]);
    }
}
