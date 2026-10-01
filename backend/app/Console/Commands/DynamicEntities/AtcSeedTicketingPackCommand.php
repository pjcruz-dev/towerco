<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\AtcTicketingPackSeedService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class AtcSeedTicketingPackCommand extends Command
{
    protected $signature = 'atc:seed-ticketing-pack
        {--tenant= : Tenant UUID (required)}';

    protected $description = 'Seed ATC Dynamic Ticketing entities/fields (greenfield; dump has no ticket tables)';

    public function handle(AtcTicketingPackSeedService $service): int
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
            $actorId = TenantUser::query()->orderBy('created_at')->value('id');
            $stats = $service->seed($actorId !== null ? (string) $actorId : null);
            $this->info(sprintf(
                'Done. entities=%d fields=%d categories=%d related_tabs=%s',
                $stats['entities'],
                $stats['fields'],
                $stats['categories'],
                $stats['related_tabs'] ? 'yes' : 'no'
            ));

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
