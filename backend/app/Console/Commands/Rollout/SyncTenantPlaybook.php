<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Console\Command;

class SyncTenantPlaybook extends Command
{
    protected $signature = 'tenants:sync-playbook
        {--domain= : Tenant domain}
        {--tenants=* : Tenant UUID(s)}
        {--playbook-version= : Playbook semver e.g. 1.0.0}
        {--policy= : Published rollout policy bundle code}
        {--with-rbac : Also refresh tenant RBAC baseline (new rollout permissions)}
    ';

    protected $description = 'Assign/sync rollout playbook version to tenant central binding and tenant DB snapshot.';

    public function handle(
        RolloutPlaybookCatalogService $catalog,
        RolloutPolicyBundleService $policyBundles,
        TenantPlaybookSyncService $sync,
        TenantRbacBaselineService $rbac,
    ): int {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $policyCode = (string) ($this->option('policy') ?: '');
        if ($policyCode !== '') {
            /** @var RolloutPolicyBundle $bundle */
            $bundle = RolloutPolicyBundle::query()
                ->where('code', $policyCode)
                ->where('status', RolloutPolicyBundle::STATUS_PUBLISHED)
                ->firstOrFail();

            foreach ($tenantIds as $tenantId) {
                /** @var Tenant $tenant */
                $tenant = Tenant::query()->findOrFail($tenantId);
                $binding = $policyBundles->assignToTenant($tenant, $bundle);
                $sync->syncBindingToTenantDatabase($tenant, $binding);
                $this->info("Synced policy {$bundle->code} → {$tenantId}");

                if ($this->option('with-rbac')) {
                    $tenant->run(static function () use ($rbac): void {
                        $rbac->ensure();
                    });
                    $this->line('  RBAC baseline refreshed.');
                }
            }

            return self::SUCCESS;
        }

        $version = $this->option('playbook-version')
            ? \App\Modules\Platform\Models\RolloutPlaybookVersion::query()
                ->where('version', (string) $this->option('playbook-version'))
                ->firstOrFail()
            : ($catalog->latestPublished() ?? $catalog->ensurePublishedV1());

        foreach ($tenantIds as $tenantId) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->findOrFail($tenantId);
            $binding = $catalog->assignToTenant($tenant, $version);
            $sync->syncBindingToTenantDatabase($tenant, $binding);
            $this->info("Synced playbook {$version->version} → {$tenantId}");

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
