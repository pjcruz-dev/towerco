<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantOffboardingService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class DeleteTenant extends Command
{
    protected $signature = 'tenants:delete
        {tenant : Tenant UUID or primary domain}
        {--force : Skip interactive confirmation}';

    protected $description = 'Permanently delete a tenant, its database, domains, and stored files.';

    public function handle(TenantOffboardingService $offboarding): int
    {
        $lookup = trim((string) $this->argument('tenant'));

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($lookup);

        if ($tenant === null) {
            $tenant = Tenant::query()
                ->whereHas('domains', static fn ($query) => $query->where('domain', $lookup))
                ->first();
        }

        if ($tenant === null) {
            $this->error("Tenant not found for \"{$lookup}\".");

            return self::FAILURE;
        }

        $tenant->loadMissing('domains');
        $domains = $tenant->domains->pluck('domain')->implode(', ');

        $this->warn('This permanently deletes:');
        $this->line("  tenant_id: {$tenant->id}");
        $this->line('  domains: '.($domains !== '' ? $domains : '—'));
        $this->line('  tenant MySQL database and all tenant-scoped files');

        if (! $this->option('force') && ! $this->confirm('Type yes to continue — this cannot be undone', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $typed = $this->ask('Enter tenant ID to confirm deletion');
            if (trim((string) $typed) !== $tenant->id) {
                $this->error('Confirmation did not match tenant ID.');

                return self::INVALID;
            }
        }

        try {
            $result = $offboarding->deleteTenant($tenant, [
                'confirmation' => $tenant->id,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::INVALID;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Failed to delete tenant: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Tenant deleted.');
        $this->line('tenant_id: '.$result['tenant_id']);
        $this->line('database_dropped: '.($result['database_dropped'] ? 'yes' : 'no'));
        $this->line('filesystem_purged: '.($result['filesystem_purged'] ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
