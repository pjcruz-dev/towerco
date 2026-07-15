<?php

declare(strict_types=1);

namespace App\Console\Commands\Documents;

use App\Models\Tenant;
use App\Modules\Documents\Services\DocumentRolloutLeasePackageMigrationService;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

class DocumentsMigrateLeasePackagesCommand extends Command
{
    protected $signature = 'documents:migrate-lease-packages
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
        {--rollout= : Optional rollout program UUID}
    ';

    protected $description = 'Migrate rollout candidate lease_package files into site document binders.';

    public function handle(
        DocumentRolloutLeasePackageMigrationService $migration,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $rolloutFilter = (string) ($this->option('rollout') ?: '');
        $totalMigrated = 0;
        $totalSkipped = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($migration, $modules, $tenant, $rolloutFilter, &$totalMigrated, &$totalSkipped): void {
                $enabled = $modules->resolveForCurrentTenant();
                if (! in_array('documents', $enabled, true) || ! in_array('sites', $enabled, true)) {
                    return;
                }

                $query = RolloutProgram::query()->whereNotNull('site_id');
                if ($rolloutFilter !== '') {
                    $query->where('id', $rolloutFilter);
                }

                foreach ($query->get() as $program) {
                    try {
                        $result = $migration->migrateRollout($program);
                        $totalMigrated += $result['migrated'];
                        $totalSkipped += $result['skipped'];

                        if ($result['migrated'] > 0 || $result['errors'] !== []) {
                            $this->line(sprintf(
                                'Tenant %s rollout %s: migrated=%d skipped=%d errors=%d',
                                $tenant->id,
                                $program->rollout_ref,
                                $result['migrated'],
                                $result['skipped'],
                                count($result['errors']),
                            ));
                        }
                    } catch (\Throwable $exception) {
                        $this->warn(sprintf(
                            'Tenant %s rollout %s failed: %s',
                            $tenant->id,
                            $program->rollout_ref,
                            $exception->getMessage(),
                        ));
                    }
                }
            });
        }

        $this->info("Lease package migration complete. {$totalMigrated} migrated, {$totalSkipped} skipped.");

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
