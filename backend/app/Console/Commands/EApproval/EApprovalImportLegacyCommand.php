<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Models\Tenant;
use App\Modules\EApproval\Services\Legacy\EApprovalLegacyImportService;
use Illuminate\Console\Command;

class EApprovalImportLegacyCommand extends Command
{
    protected $signature = 'e-approval:import-legacy
        {--tenant= : Tenant UUID (required)}
        {--connection= : Legacy DB connection name (default: config toweros.e_approval.legacy_connection)}
        {--dry-run : Count rows without writing}
        {--only= : Comma-separated: users,forms,submissions,master-data,settings,delegations}
    ';

    protected $description = 'Import legacy formbuilder MySQL data into E-Approval tenant tables.';

    public function handle(EApprovalLegacyImportService $importer): int
    {
        $tenantId = (string) ($this->option('tenant') ?: '');
        if ($tenantId === '') {
            $this->error('Provide --tenant=<uuid>.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $connection = (string) ($this->option('connection')
            ?: config('toweros.e_approval.legacy_connection', 'legacy_formbuilder'));

        $onlyRaw = trim((string) ($this->option('only') ?: ''));
        $only = $onlyRaw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $onlyRaw))));

        $dryRun = (bool) $this->option('dry-run');

        tenancy()->initialize($tenant);

        try {
            if ($dryRun) {
                $this->warn('Dry run — no tenant data will be written.');
            }

            $result = $importer->run($connection, $dryRun, $only);
            $this->table(['Metric', 'Count'], collect($result->toArray())
                ->except('warnings')
                ->map(fn ($v, $k) => [$k, is_scalar($v) ? (string) $v : json_encode($v)])
                ->values()
                ->all());

            foreach ($result->warnings as $warning) {
                $this->line("<comment>{$warning}</comment>");
            }

            $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            tenancy()->end();
        }
    }
}
