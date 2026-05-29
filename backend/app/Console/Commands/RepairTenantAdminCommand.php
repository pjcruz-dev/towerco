<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantAdminBootstrapService;
use Illuminate\Console\Command;

final class RepairTenantAdminCommand extends Command
{
    protected $signature = 'tenants:repair-admin
                            {--tenant= : Tenant UUID}
                            {--domain= : Override bootstrap email domain}
                            {--all : Repair every tenant missing an admin user}';

    protected $description = 'Ensure each tenant has a bootstrap administrator (admin@{domain}).';

    public function handle(TenantAdminBootstrapService $adminBootstrap): int
    {
        $tenantId = $this->option('tenant');
        $all = (bool) $this->option('all');

        if (! $tenantId && ! $all) {
            $this->error('Pass --tenant={uuid} or --all');

            return self::FAILURE;
        }

        $tenants = $all
            ? Tenant::query()->with('domains')->get()
            : Tenant::query()->with('domains')->whereKey($tenantId)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $domain = (string) ($this->option('domain') ?: $tenant->domains()->first()?->domain ?: '');
            if ($domain === '') {
                $this->warn("Skipping {$tenant->id}: no domain registered.");

                continue;
            }

            $hasUsers = $tenant->run(fn () => \App\Modules\Identity\Models\TenantUser::query()->exists());

            if ($hasUsers && ! $this->option('domain')) {
                $this->line("[{$tenant->environment}] {$domain} — admin already present, skipped.");

                continue;
            }

            $admin = $adminBootstrap->bootstrap($tenant, $domain);
            $this->info("[{$tenant->environment}] {$domain} — ensured {$admin['email']}");
        }

        return self::SUCCESS;
    }
}
