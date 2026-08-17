<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\EApproval\Services\EApprovalFormTemplateService;
use Illuminate\Console\Command;

class RepairFinanceFormComputedFieldsCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'e-approval:repair-finance-computed-fields
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
        {--form= : Optional form UUID}
    ';

    protected $description = 'Upgrade cash advance, liquidation, and reimbursement forms to the amount-ladder workflow and merge missing template fields.';

    public function handle(): int
    {
        $tenant = $this->resolveTenantFromOptions();
        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $formId = $this->option('form');
        $upgraded = $tenant->run(function () use ($formId): int {
            return app(EApprovalFormTemplateService::class)->upgradeFinanceAmountWorkflowForms(
                is_string($formId) && $formId !== '' ? $formId : null,
            );
        });

        $this->info("Upgraded {$upgraded} cash advance / liquidation / reimbursement form(s) on tenant [{$tenant->id}].");

        return self::SUCCESS;
    }
}
