<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Console\Command;

class PublishRolloutPolicyBundle extends Command
{
    protected $signature = 'rollout:policy:publish {code : Policy bundle code slug}';

    protected $description = 'Publish a draft rollout policy bundle after SLA validation.';

    public function handle(RolloutPolicyBundleService $service): int
    {
        $code = (string) $this->argument('code');

        /** @var RolloutPolicyBundle|null $bundle */
        $bundle = RolloutPolicyBundle::query()->where('code', $code)->first();
        if ($bundle === null) {
            $this->error("Policy bundle [{$code}] not found.");

            return self::FAILURE;
        }

        $published = $service->publish($service->find($bundle->id));
        $this->info("Published rollout policy {$published->code} — {$published->name}");

        return self::SUCCESS;
    }
}
