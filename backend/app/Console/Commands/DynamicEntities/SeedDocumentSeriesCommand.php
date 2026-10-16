<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\DocumentSeriesSeedService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class SeedDocumentSeriesCommand extends Command
{
    protected $signature = 'dyn:seed-document-series {--tenant= : Tenant UUID}';

    protected $description = 'Add the Document Series entity, its fields, and the series rows from the legacy list';

    public function handle(DocumentSeriesSeedService $service): int
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

        tenancy()->initialize($tenant);

        try {
            $actor = TenantUser::query()->where('is_active', true)->orderBy('created_at')->first();
            if (! $actor instanceof TenantUser) {
                $this->error('No active user to own the records.');

                return self::FAILURE;
            }

            $stats = $service->seed($actor);
            $this->info(sprintf(
                'Done. entities=%d fields=%d rows=%d',
                $stats['entities'],
                $stats['fields'],
                $stats['rows'],
            ));

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
