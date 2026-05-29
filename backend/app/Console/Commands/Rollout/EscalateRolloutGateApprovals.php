<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Rollout\Services\RolloutGateApprovalEscalationService;
use Illuminate\Console\Command;

class EscalateRolloutGateApprovals extends Command
{
    protected $signature = 'rollout:gate-approvals:escalate
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Send escalation emails for gate approval steps pending beyond configured working days.';

    public function handle(RolloutGateApprovalEscalationService $escalation): int
    {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($tenantIds as $tenantId) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($escalation, $tenant, &$total): void {
                $due = $escalation->dueForEscalation();
                foreach ($due as $request) {
                    $escalation->escalate($request);
                    $total++;
                }

                if ($due !== []) {
                    $this->line("Tenant {$tenant->id}: escalated ".count($due).' request(s).');
                }
            });
        }

        $this->info("Escalation complete. {$total} notification(s) sent.");

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
        if ($domain !== '') {
            $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

            return $tenant ? [(string) $tenant->id] : [];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }
}
