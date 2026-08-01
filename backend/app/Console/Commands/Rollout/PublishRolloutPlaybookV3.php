<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Rollout\Data\RolloutPlaybookV3Definition;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use Illuminate\Console\Command;

class PublishRolloutPlaybookV3 extends Command
{
    protected $signature = 'rollout-playbook:publish-v3';

    protected $description = 'Publish rollout playbook v3.0.0 to platform catalog and refresh tenant latest-version pointers.';

    public function handle(
        RolloutPlaybookCatalogService $catalog,
        TenantPlaybookSyncService $sync,
    ): int {
        $version = $catalog->publishVersion(RolloutPlaybookV3Definition::VERSION);
        $sync->propagateLatestVersionToAllTenants();

        $this->info("Published playbook v{$version->version} — {$version->name}");

        return self::SUCCESS;
    }
}
