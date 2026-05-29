<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;

trait SeedsTenantRolloutPlaybook
{
    protected function seedTenantRolloutPlaybook(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        tenancy()->end();
    }
}
