<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\AtcImportVerifyService;
use Illuminate\Console\Command;

final class AtcVerifyImportCommand extends Command
{
    protected $signature = 'atc:verify-import
        {--tenant= : Tenant UUID (required)}
        {--dump= : Absolute path to Metacoresoft SQL dump}
        {--pack=all : Pack: phase1, phase2, phase3, phase5, all}
        {--slug=* : Optional entity slug(s); overrides --pack}
        {--tolerance=0 : Allowed absolute delta between dump eligible and dyn imported}';

    protected $description = 'Reconcile Metacoresoft dump row counts vs Dynamic Entity ETL records (staging/cutover)';

    public function handle(AtcImportVerifyService $service): int
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

        $dump = (string) ($this->option('dump') ?: env('ATC_METACORE_DUMP_PATH', ''));
        if ($dump === '') {
            $this->error('Provide --dump= or set ATC_METACORE_DUMP_PATH.');

            return self::FAILURE;
        }

        /** @var list<string> $slugs */
        $slugs = array_values(array_filter(array_map('strval', (array) $this->option('slug'))));
        $pack = strtolower((string) $this->option('pack'));
        $tolerance = max(0, (int) $this->option('tolerance'));

        tenancy()->initialize($tenant);

        try {
            $result = $service->verify(
                $dump,
                $slugs !== [] ? $slugs : null,
                $pack,
                $tolerance,
                $tenantId,
            );

            $this->info(sprintf(
                'ATC verify pack=%s dump=%s (%s bytes) tolerance=%d',
                $result['pack'],
                basename($result['dump_path']),
                number_format($result['dump_bytes']),
                $result['tolerance']
            ));

            $table = [];
            foreach ($result['rows'] as $row) {
                $table[] = [
                    $row['slug'],
                    $row['status'],
                    (string) $row['dump_eligible'],
                    (string) $row['dyn_imported'],
                    $row['delta'] === null ? '—' : (string) $row['delta'],
                    (string) $row['dyn_total'],
                    (string) ($row['note'] ?? ''),
                ];
            }
            $this->table(
                ['slug', 'status', 'dump_eligible', 'dyn_imported', 'delta', 'dyn_total', 'note'],
                $table
            );

            $s = $result['summary'];
            $this->line(sprintf(
                'Summary: entities=%d matched=%d mismatched=%d missing_entity=%d missing_table=%d dump_eligible=%d dyn_imported=%d',
                $s['entities'],
                $s['matched'],
                $s['mismatched'],
                $s['missing_entity'],
                $s['missing_table'],
                $s['dump_eligible'],
                $s['dyn_imported']
            ));

            if (! $result['ok']) {
                $this->error('Reconcile FAILED — investigate mismatches before production cutover.');

                return self::FAILURE;
            }

            $this->info('Reconcile OK.');

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
