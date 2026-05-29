<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Console\Command;

class AssignRolloutPolicyToTenant extends Command
{
    protected $signature = 'tenants:assign-rollout-policy
        {--domain= : Tenant domain}
        {--tenants=* : Tenant UUID(s)}
        {--policy= : Published rollout policy bundle code}
        {--with-rbac : Also refresh tenant RBAC baseline}
    ';

    protected $description = 'Assign a published rollout policy bundle (playbook + timeline + gate approvals) to tenant(s).';

    public function handle(
        RolloutPolicyBundleService $policyBundles,
        TenantPlaybookSyncService $sync,
        TenantRbacBaselineService $rbac,
    ): int {
        $code = (string) ($this->option('policy') ?: '');
        if ($code === '') {
            $this->error('Provide --policy=code for a published rollout policy bundle.');

            return self::FAILURE;
        }

        /** @var RolloutPolicyBundle|null $bundle */
        $bundle = RolloutPolicyBundle::query()->where('code', $code)->first();
        if ($bundle === null) {
            $this->error("Policy bundle [{$code}] not found.");

            return self::FAILURE;
        }

        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        foreach ($tenantIds as $tenantId) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->findOrFail($tenantId);
            $binding = $policyBundles->assignToTenant($tenant, $policyBundles->find($bundle->id));
            $sync->syncBindingToTenantDatabase($tenant, $binding);
            $this->info("Assigned policy {$code} → {$tenantId}");

            if ($this->option('with-rbac')) {
                $tenant->run(static function () use ($rbac): void {
                    $rbac->ensure();
                });
                $this->line('  RBAC baseline refreshed.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        $explicit = array_values(array_filter((array) $this->option('tenants'), static fn ($id) => is_string($id) && $id !== ''));
        if ($explicit !== []) {
            return $explicit;
        }

        $domain = (string) ($this->option('domain') ?: 'alliance.localhost');
        $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

        return $tenant ? [(string) $tenant->id] : [];
    }
}
