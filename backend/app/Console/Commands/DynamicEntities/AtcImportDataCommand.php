<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\AtcDataImportService;
use App\Modules\DynamicEntities\Services\AtcImportVerifyService;
use App\Modules\DynamicEntities\Support\AtcFinancePack;
use App\Modules\DynamicEntities\Support\AtcPmRelatedTabs;
use App\Modules\DynamicEntities\Support\AtcProcurementPack;
use App\Modules\DynamicEntities\Support\AtcTicketingPack;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use Illuminate\Console\Command;

final class AtcImportDataCommand extends Command
{
    protected $signature = 'atc:import-data
        {--tenant= : Tenant UUID (required)}
        {--dump= : Absolute path to Metacoresoft SQL dump}
        {--pack=phase1 : Import pack: phase1, phase2, phase3, phase5, all}
        {--slug=* : Optional entity slug(s); overrides --pack}
        {--dry-run : Count eligible dump rows without writing}';

    protected $description = 'Import Metacoresoft dat_* rows into Dynamic Entity records';

    public function handle(
        AtcDataImportService $service,
        AtcImportVerifyService $verify,
        StructuredAuditLogWriter $auditWriter,
    ): int {
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

        $dump = (string) ($this->option('dump') ?: env('ATC_METACORE_DUMP_PATH', ''));
        if ($dump === '') {
            $this->error('Provide --dump= or set ATC_METACORE_DUMP_PATH.');

            return self::FAILURE;
        }

        /** @var list<string> $slugs */
        $slugs = array_values(array_filter(array_map('strval', (array) $this->option('slug'))));
        $pack = strtolower((string) $this->option('pack'));
        if ($slugs === []) {
            $slugs = match ($pack) {
                'phase2', 'procurement' => AtcProcurementPack::phase2EntitySlugs(),
                'phase3', 'finance' => AtcFinancePack::phase3EntitySlugs(),
                'phase5', 'ticketing' => AtcTicketingPack::phase5EntitySlugs(),
                'all' => AtcTicketingPack::allThroughPhase5(),
                default => AtcPmRelatedTabs::phase1EntitySlugs(),
            };
        }

        if (in_array($pack, ['phase5', 'ticketing'], true)) {
            $this->warn('Phase 5 ticketing has no dat_* tables in the current dump. Prefer: php artisan atc:seed-ticketing-pack --tenant=...');
        }

        $dryRun = (bool) $this->option('dry-run');

        tenancy()->initialize($tenant);

        try {
            if ($dryRun) {
                $this->warn('Dry run — no records will be written.');
                $this->line('Entities: '.implode(', ', $slugs));
                $preview = $verify->previewImport($dump, $slugs, $pack);
                $table = [];
                foreach ($preview['rows'] as $row) {
                    $table[] = [
                        $row['slug'],
                        $row['status'],
                        (string) $row['would_import'],
                        (string) $row['would_skip'],
                    ];
                }
                $this->table(['slug', 'status', 'would_import', 'would_skip'], $table);
                $this->info(sprintf(
                    'Dry run complete. entities=%d would_import=%d would_skip=%d',
                    $preview['entities'],
                    $preview['would_import'],
                    $preview['would_skip']
                ));
                if ($preview['missing'] !== []) {
                    $this->warn('Missing: '.implode(', ', $preview['missing']));
                }

                $auditWriter->write('atc_etl', 'atc.import.dry_run', [
                    'tenant_id' => $tenantId,
                    'pack' => $pack,
                    'dump_path' => basename($dump),
                    'would_import' => $preview['would_import'],
                    'would_skip' => $preview['would_skip'],
                    'entities' => $preview['entities'],
                ]);

                return self::SUCCESS;
            }

            $this->comment('Production tip: take a tenant DB backup before cutover (tenants:backup-schedule / ops runbook).');
            $this->info('Importing data from '.$dump);
            $this->line('Entities: '.implode(', ', $slugs));
            $stats = $service->importFromDump($dump, $slugs);
            $this->info(sprintf(
                'Done. entities=%d records=%d skipped=%d',
                $stats['entities'],
                $stats['records'],
                $stats['skipped']
            ));
            if ($stats['missing'] !== []) {
                $this->warn('Missing / empty tables: '.implode(', ', $stats['missing']));
            }

            $auditWriter->write('atc_etl', 'atc.import.completed', [
                'tenant_id' => $tenantId,
                'pack' => $pack,
                'dump_path' => basename($dump),
                'entities' => $stats['entities'],
                'records' => $stats['records'],
                'skipped' => $stats['skipped'],
                'missing' => $stats['missing'],
            ]);

            $this->line('Next: php artisan atc:verify-import --tenant='.$tenantId.' --pack='.$pack.' --dump=...');

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
