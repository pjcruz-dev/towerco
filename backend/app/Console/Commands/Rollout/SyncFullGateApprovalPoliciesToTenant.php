<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Rollout\Data\RolloutGateApprovalPolicyFullCoverage;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Services\RolloutGateApprovalPolicyService;
use Illuminate\Console\Command;

class SyncFullGateApprovalPoliciesToTenant extends Command
{
    protected $signature = 'tenants:sync-full-gate-approval-policies
        {--domain= : Tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Apply full gate-approval policies (all phases enabled) directly to tenant rollout playbook config.';

    public function handle(RolloutGateApprovalPolicyService $gatePolicies): int
    {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found. Use --domain= or --tenants=');

            return self::FAILURE;
        }

        foreach ($tenantIds as $tenantId) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->findOrFail($tenantId);

            $tenant->run(function () use ($gatePolicies, $tenantId): void {
                $config = TenantRolloutPlaybookConfig::query()->first();
                if ($config === null) {
                    $this->error("Tenant {$tenantId} has no rollout playbook config. Run tenants:sync-playbook first.");

                    return;
                }

                $templates = $config->playbook_snapshot['timeline_templates'] ?? [];
                if (! is_array($templates)) {
                    $this->error("Tenant {$tenantId} playbook snapshot has no timeline_templates.");

                    return;
                }

                /** @var array<string, list<array<string, mixed>>> $templates */
                $policies = RolloutGateApprovalPolicyFullCoverage::fromTimelineTemplates($templates);
                $gatePolicies->saveTenantPolicies($policies);

                $counts = collect($policies)->map(static fn (array $rows) => count($rows));
                $this->info("Tenant {$tenantId}: saved gate policies — ".$counts->map(static fn ($n, $k) => "{$k}:{$n}")->implode(', '));
            });
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

        $domain = (string) ($this->option('domain') ?: '');
        if ($domain === '') {
            return [];
        }

        $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

        return $tenant ? [(string) $tenant->id] : [];
    }
}
