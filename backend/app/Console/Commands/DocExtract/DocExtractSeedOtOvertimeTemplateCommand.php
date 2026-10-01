<?php

declare(strict_types=1);

namespace App\Console\Commands\DocExtract;

use App\Models\Tenant;
use App\Modules\DocExtract\Services\DocExtractOtOvertimeTemplateInstaller;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class DocExtractSeedOtOvertimeTemplateCommand extends Command
{
    protected $signature = 'doc-extract:seed-ot-overtime-template
                            {--domain= : Limit to a single tenant domain}
                            {--tenants=* : Limit to specific tenant IDs}';

    protected $description = 'Create or refresh the published OT overtime (Agency / DocuSign) DocExtract template';

    public function handle(DocExtractOtOvertimeTemplateInstaller $installer): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($this->option('domain')) {
            $query->whereHas('domains', fn ($q) => $q->where('domain', $this->option('domain')));
        }
        $tenantIds = array_values(array_filter(array_map('strval', (array) $this->option('tenants'))));
        if ($tenantIds !== []) {
            $query->whereIn('id', $tenantIds);
        }

        $count = 0;
        foreach ($query->cursor() as $tenant) {
            /** @var Tenant $tenant */
            $result = $tenant->run(function () use ($installer): array {
                $actor = TenantUser::query()
                    ->where('is_active', true)
                    ->orderBy('created_at')
                    ->first();

                return $installer->ensure($actor instanceof TenantUser ? $actor : null);
            });

            $action = $result['created'] ? 'created' : 'updated';
            $this->info(sprintf(
                'Tenant %s: %s template "%s" (%s).',
                $tenant->id,
                $action,
                $result['template']->name,
                $result['template']->id,
            ));
            $count++;
        }

        if ($count === 0) {
            $this->warn('No matching tenants.');

            return self::FAILURE;
        }

        $this->info(sprintf('Seeded OT overtime template on %d tenant(s).', $count));

        return self::SUCCESS;
    }
}
