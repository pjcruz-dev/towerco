<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\AtcMetaImportService;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use Illuminate\Console\Command;

final class AtcImportMetaCommand extends Command
{
    protected $signature = 'atc:import-meta
        {--tenant= : Tenant UUID (required)}
        {--dump= : Absolute path to Metacoresoft SQL dump}';

    protected $description = 'Import Metacoresoft entities/fields metadata into Dynamic Entities';

    public function handle(AtcMetaImportService $service, StructuredAuditLogWriter $auditWriter): int
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

        tenancy()->initialize($tenant);

        try {
            $this->info('Importing metadata from '.$dump);
            $stats = $service->importFromDump($dump);
            $this->info(sprintf(
                'Done. entities=%d fields=%d skipped=%d',
                $stats['entities'],
                $stats['fields'],
                $stats['skipped']
            ));

            $auditWriter->write('atc_etl', 'atc.import_meta.completed', [
                'tenant_id' => $tenantId,
                'dump_path' => basename($dump),
                'entities' => $stats['entities'],
                'fields' => $stats['fields'],
                'skipped' => $stats['skipped'],
            ]);

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
