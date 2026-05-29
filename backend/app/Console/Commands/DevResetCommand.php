<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantOffboardingService;
use Illuminate\Console\Command;

/**
 * Dev-only: remove all tenants (and their MySQL databases) while keeping platform catalog data.
 */
final class DevResetCommand extends Command
{
    protected $signature = 'toweros:dev-reset
        {--tenants-only : Remove every tenant; keep users, playbooks, and policy bundles (default)}
        {--force : Skip confirmation}';

    protected $description = 'Reset local dev data: remove all tenants or guide a full Docker MySQL wipe.';

    public function handle(TenantOffboardingService $offboarding): int
    {
        if (! $this->option('tenants-only')) {
            $this->components->warn('Full MySQL wipe cannot run from Artisan alone (Docker volume).');
            $this->line('From the repo root on Windows:');
            $this->line('  scripts\\docker-dev-fresh.cmd');
            $this->line('Or: npm run dev:fresh');
            $this->line('');
            $this->line('That recreates central schema, superadmin, and default published playbook (db:seed).');
            $this->line('Custom policy bundles created in the UI are not restored unless you back them up first.');

            return self::SUCCESS;
        }

        if (! app()->environment('local')) {
            $this->error('toweros:dev-reset --tenants-only is only allowed when APP_ENV=local.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()->with('domains')->orderBy('created_at')->get();
        if ($tenants->isEmpty()) {
            $this->info('No tenants to remove.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tenant ID', 'Environment', 'Domains'],
            $tenants->map(fn (Tenant $t) => [
                $t->id,
                $t->environment ?? '—',
                $t->domains->pluck('domain')->implode(', ') ?: '—',
            ])->all(),
        );

        if (! $this->option('force') && ! $this->confirm('Delete ALL tenants above (databases + central rows)?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($tenants as $tenant) {
            try {
                $offboarding->deleteTenant($tenant, [
                    'confirmation' => $tenant->id,
                    'cascade' => true,
                ]);
                $deleted++;
                $this->line("Removed {$tenant->id}");
            } catch (\Throwable $e) {
                $this->error("Failed {$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} tenant(s). Kept: users, rollout_playbook_versions, rollout_policy_bundles.");
        $this->line('Create a new tenant from http://localhost:3001/platform/tenants/create');

        return self::SUCCESS;
    }
}
