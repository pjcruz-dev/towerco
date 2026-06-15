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

    protected $description = 'Seed Alliance-style demo data (sites, modules, users) into tenant database(s). Idempotent.';

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

            $this->components->twoColumnDetail('  Sites', (string) $tenant->run(fn () => \App\Modules\Sites\Models\Site::query()->count()));
            $this->components->twoColumnDetail('  Projects', (string) $tenant->run(fn () => \App\Modules\ProjectOne\Models\Project::query()->count()));
            $this->components->twoColumnDetail('  Towers', (string) $tenant->run(fn () => \App\Modules\TowerOne\Models\Tower::query()->count()));
            $this->components->twoColumnDetail('  Fiber routes', (string) $tenant->run(fn () => \App\Modules\FiberOne\Models\FiberRoute::query()->count()));
            $this->components->twoColumnDetail('  Assets', (string) $tenant->run(fn () => \App\Modules\AssetOne\Models\Asset::query()->count()));
            $this->components->twoColumnDetail('  Users', (string) $tenant->run(fn () => \App\Modules\Identity\Models\TenantUser::query()->count()));
            $rolloutCount = $tenant->run(function (): int {
                if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('rollout_programs')) {
                    return 0;
                }

                return \App\Modules\Rollout\Models\RolloutProgram::query()->count();
            });
            if ($rolloutCount > 0) {
                $this->components->twoColumnDetail('  Rollouts', (string) $rolloutCount);
            }
        }

        $this->newLine();
        $this->comment('Demo logins (password: password):');
        $this->line('  admin@alliance.localhost        — tenant_admin');
        $this->line('  manager@alliance.localhost      — manager');
        $this->line('  project.lead@alliance.localhost — manager');
        $this->line('  finance@alliance.localhost      — finance');
        $this->line('  ops.viewer@alliance.localhost   — viewer');
        $this->line('Tenant URL: http://alliance.localhost/login');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        $explicit = array_values(array_filter((array) $this->option('tenants'), static fn ($id) => is_string($id) && $id !== ''));

        if ($explicit !== []) {
            return $explicit;
        }

        $domain = strtolower(trim((string) ($this->option('domain') ?: config('toweros.demo.tenant_domain', 'alliance.localhost'))));

        $tenant = Tenant::query()
            ->whereHas('domains', static fn ($q) => $q->where('domain', $domain))
            ->first();

        if ($tenant === null && config('toweros.demo.tenant_id')) {
            $configured = (string) config('toweros.demo.tenant_id');
            if (Tenant::query()->whereKey($configured)->exists()) {
                return [$configured];
            }
        }

        return $tenant !== null ? [(string) $tenant->id] : [];
    }

    private function ensureAllianceCentralMetadata(Tenant $tenant): void
    {
        $domain = strtolower((string) ($tenant->domains()->first()?->domain ?? ''));

        if (! str_contains($domain, 'alliance')) {
            return;
        }

        $tenant->slug = $tenant->slug ?: 'atc';
        $tenant->brand_domain = $tenant->brand_domain ?: 'alliancetowers.com';
        $tenant->tco_sequence_prefix = $tenant->tco_sequence_prefix ?: 'A';
        $tenant->environment = $tenant->environment ?: 'local';
        $tenant->save();

        $this->line('  Central metadata: slug=atc, brand_domain=alliancetowers.com, tco_sequence_prefix=A');
    }
}
