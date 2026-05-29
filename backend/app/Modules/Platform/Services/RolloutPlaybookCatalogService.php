<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Rollout\Data\RolloutPlaybookDefinitionRegistry;
use App\Modules\Rollout\Data\RolloutPlaybookV1Definition;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RolloutPlaybookCatalogService
{
    public function ensurePublishedV1(): RolloutPlaybookVersion
    {
        $payload = RolloutPlaybookV1Definition::payload();

        /** @var RolloutPlaybookVersion $version */
        $version = RolloutPlaybookVersion::query()->updateOrCreate(
            ['version' => $payload['version']],
            array_merge($payload, [
                'published_at' => now(),
            ]),
        );

        return $version;
    }

    public function publishVersion(string $version): RolloutPlaybookVersion
    {
        if (! in_array($version, RolloutPlaybookDefinitionRegistry::supportedVersions(), true)) {
            throw ValidationException::withMessages([
                'version' => [__('Unsupported playbook version.')],
            ]);
        }

        $payload = RolloutPlaybookDefinitionRegistry::payloadForVersion($version);
        unset($payload['version']);

        /** @var RolloutPlaybookVersion $record */
        $record = RolloutPlaybookVersion::query()->updateOrCreate(
            ['version' => $version],
            array_merge($payload, [
                'status' => 'published',
                'published_at' => now(),
            ]),
        );

        return $record->fresh();
    }

    /**
     * @return list<RolloutPlaybookVersion>
     */
    public function listPublished(): array
    {
        return RolloutPlaybookVersion::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->get()
            ->all();
    }

    public function latestPublished(): ?RolloutPlaybookVersion
    {
        return RolloutPlaybookVersion::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->first();
    }

    public function assignToTenant(Tenant $tenant, RolloutPlaybookVersion $version, string $upgradePolicy = 'new_rollouts_only'): TenantPlaybookBinding
    {
        if ($version->status !== 'published') {
            throw ValidationException::withMessages([
                'playbook_version_id' => [__('Only published playbook versions can be assigned.')],
            ]);
        }

        /** @var TenantPlaybookBinding $binding */
        $binding = TenantPlaybookBinding::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'playbook_version_id' => $version->id,
                'upgrade_policy' => $upgradePolicy,
                'assigned_at' => now(),
            ],
        );

        return $binding->fresh(['playbookVersion']);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RolloutPlaybookVersion $version): array
    {
        return [
            'version' => $version->version,
            'name' => $version->name,
            'sla_working_days_only' => $version->sla_working_days_only,
            'delivery_periods' => $version->delivery_periods,
            'timeline_templates' => $version->timeline_templates,
            'milestone_cycle_targets' => $version->milestone_cycle_targets,
            'form_schemas' => $version->form_schemas,
        ];
    }
}
