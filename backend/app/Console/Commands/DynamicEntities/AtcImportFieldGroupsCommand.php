<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\AtcFieldGroupImportService;
use Illuminate\Console\Command;

final class AtcImportFieldGroupsCommand extends Command
{
    protected $signature = 'atc:import-field-groups
        {--tenant= : Tenant UUID (required)}
        {--dump= : Absolute path to Metacoresoft SQL dump}
        {--replace : Wipe existing groups and re-import from dump}';

    protected $description = 'Import Metacoresoft sys_field_groups into Dynamic Entity field groups';

    public function handle(AtcFieldGroupImportService $service): int
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

        $dump = (string) ($this->option('dump') ?: env('ATC_METACORE_DUMP_PATH', 'storage/app/imports/atc-dump.sql'));
        if ($dump === '' || ! is_readable($dump)) {
            $this->error('Dump not readable. Provide --dump= or set ATC_METACORE_DUMP_PATH.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            $this->info('Importing field groups from '.$dump);
            $stats = $service->importFromDump($dump, (bool) $this->option('replace'));
            $this->info(sprintf(
                'Done. entities=%d groups=%d fields_assigned=%d skipped=%d',
                $stats['entities'],
                $stats['groups'],
                $stats['fields_assigned'],
                $stats['skipped']
            ));

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
