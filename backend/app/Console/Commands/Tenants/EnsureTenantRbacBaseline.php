<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Console\Command;

class EnsureTenantRbacBaseline extends Command
{
    protected $signature = 'tenants:ensure-rbac
        {--domain= : Tenant domain (e.g. alliance.localhost)}
        {--tenants=* : Tenant UUID(s); if omitted, all tenants}
    ';

    protected $description = 'Ensure tenant RBAC permissions and baseline roles (must run inside each tenant DB).';

    public function handle(TenantRbacBaselineService $rbac): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $label = $tenant->domains()->first()?->domain ?? $tenant->id;
            $tenant->run(static function () use ($rbac): void {
                $rbac->ensure();
            });
            $this->info("RBAC baseline ensured → {$label} ({$tenant->id})");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Tenant>
     */
    private function resolveTenants(): array
    {
        $explicit = array_values(array_filter((array) $this->option('tenants'), static fn ($id) => is_string($id) && $id !== ''));
        if ($explicit !== []) {
            return Tenant::query()->whereIn('id', $explicit)->get()->all();
        }

        $domain = $this->option('domain');
        if (is_string($domain) && $domain !== '') {
            $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

            return $tenant ? [$tenant] : [];
        }

        return Tenant::query()->get()->all();
    }
}
