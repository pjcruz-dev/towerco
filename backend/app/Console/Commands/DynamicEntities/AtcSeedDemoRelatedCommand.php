<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\AtcDemoRelatedSeedService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class AtcSeedDemoRelatedCommand extends Command
{
    protected $signature = 'atc:seed-demo-related
        {--tenant= : Tenant UUID (required)}
        {--limit=25 : Max tower sites to attach demo children to}
        {--per-site=1 : How many demo sets to create per site (1-5)}
        {--dry-run : Preview counts without writing}
        {--skip-ticketing-schema : Do not run atc ticketing pack seed first}';

    protected $description = 'Seed demo Dynamic Entity data (Cost & Capital / Procurement / Finance / Ticketing / Reference) linked to existing Tower Sites';

    public function handle(AtcDemoRelatedSeedService $service): int
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
            $actor = TenantUser::query()->orderBy('created_at')->first();
            if (! $actor instanceof TenantUser) {
                $this->error('No tenant user found to attribute demo records.');

                return self::FAILURE;
            }

            $stats = $service->seed(
                actor: $actor,
                limitSites: (int) $this->option('limit'),
                perSite: (int) $this->option('per-site'),
                dryRun: (bool) $this->option('dry-run'),
                ensureTicketingSchema: ! (bool) $this->option('skip-ticketing-schema'),
            );

            $this->info(sprintf(
                '%s sites=%d masters_created=%d masters_skipped=%d linked_created=%d linked_skipped=%d',
                $stats['dry_run'] ? '[dry-run]' : 'Done.',
                $stats['sites'],
                $stats['masters_created'],
                $stats['masters_skipped'],
                $stats['linked_created'],
                $stats['linked_skipped'],
            ));

            if ($stats['missing_entities'] !== []) {
                $this->warn('Missing entities (import meta first): '.implode(', ', $stats['missing_entities']));
            }

            foreach ($stats['sample_errors'] as $err) {
                $this->warn('  · '.$err);
            }

            if ($stats['sites'] === 0) {
                $this->warn('No tower_sites records found. Import/create Sites first, then re-run.');
            }

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
