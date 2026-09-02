<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use Database\Seeders\AllianceDemoSeeder;
use Illuminate\Console\Command;

class SeedAllianceDemo extends Command
{
    protected $signature = 'tenants:seed-demo
        {--domain= : Tenant domain (default: config toweros.demo.tenant_domain)}
        {--tenants=* : Tenant UUID(s); overrides --domain lookup}
        {--billing : Set central plan_tier=professional and seat_limit=50 on matched tenant(s)}
    ';

    protected $description = 'Seed Alliance-style demo data into tenant database(s). Idempotent no-op stub after module removals.';

    public function handle(): int
    {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $domain = (string) ($this->option('domain') ?: config('toweros.demo.tenant_domain', 'alliance.localhost'));
            $this->error("No tenant found for domain [{$domain}]. Create the tenant first or pass --tenants=UUID.");

            return self::FAILURE;
        }

        foreach ($tenantIds as $tenantId) {
            try {
                /** @var Tenant $tenant */
                $tenant = Tenant::query()->findOrFail($tenantId);
            } catch (\Throwable) {
                $this->error("Tenant not found: {$tenantId}");

                continue;
            }

            $domain = $tenant->domains()->first()?->domain ?? $tenantId;
            $this->info("Seeding demo data for {$domain} ({$tenantId})…");

            $tenant->run(function (): void {
                $this->call(AllianceDemoSeeder::class);
            });

            if ($this->option('billing')) {
                $tenant->plan_tier = 'professional';
                $tenant->subscription_status = 'active';
                $tenant->seat_limit = 50;
                $tenant->save();
                $this->line('  Central billing set to professional / 50 seats.');
            }

            $this->ensureAllianceCentralMetadata($tenant);

            $this->components->twoColumnDetail(
                '  Users',
                (string) $tenant->run(fn () => \App\Modules\Identity\Models\TenantUser::query()->count()),
            );
        }

        $this->newLine();
        $this->comment('AllianceDemoSeeder is a no-op (legacy modules removed).');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        $explicit = array_values(array_filter(array_map('strval', (array) $this->option('tenants'))));
        if ($explicit !== []) {
            return $explicit;
        }

        $domain = (string) ($this->option('domain') ?: config('toweros.demo.tenant_domain', 'alliance.localhost'));

        return Tenant::query()
            ->whereHas('domains', static fn ($q) => $q->where('domain', $domain))
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    private function ensureAllianceCentralMetadata(Tenant $tenant): void
    {
        $dirty = false;
        if ($tenant->slug === null || $tenant->slug === '') {
            $tenant->slug = 'alliance';
            $dirty = true;
        }
        if ($dirty) {
            $tenant->save();
        }
    }
}
