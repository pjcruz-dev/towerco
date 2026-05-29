<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Modules\Tenancy\Services\TenantOnboardingService;
use App\Modules\Tenancy\Support\InitialAdminExposure;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateTenant extends Command
{
    protected $signature = 'tenants:create
        {domain : Tenant domain (e.g. acme.localhost)}
        {--tenant_id= : Optional tenant UUID/string id}
        {--migrate : Run tenant migrations after creating}
        {--seed : Seed after migrating (only if --migrate)}
    ';

    protected $description = 'Create a tenant and attach its primary domain.';

    public function handle(TenantOnboardingService $service): int
    {
        $domain = (string) $this->argument('domain');
        $tenantId = $this->option('tenant_id');
        $migrate = (bool) $this->option('migrate');
        $seed = (bool) $this->option('seed');

        if ($seed && ! $migrate) {
            $this->error('--seed requires --migrate.');
            return self::INVALID;
        }

        if ($tenantId !== null && $tenantId !== '' && ! is_string($tenantId)) {
            $this->error('Invalid --tenant_id value.');
            return self::INVALID;
        }

        if (is_string($tenantId) && $tenantId !== '' && Str::length($tenantId) > 255) {
            $this->error('--tenant_id is too long.');
            return self::INVALID;
        }

        try {
            $result = $service->createTenant([
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'migrate' => $migrate,
                'seed' => $seed,
            ]);
            $tenant = $result['tenant'];
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::INVALID;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Failed to create tenant: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Tenant created.');
        $this->line('tenant_id: '.$tenant->id);
        $this->line('domain: '.$tenant->domains()->first()?->domain);

        if ($migrate) {
            $this->info('Tenant migrations executed (see output above).');
            if (! empty($result['initial_admin'])) {
                $admin = InitialAdminExposure::forTransport($result['initial_admin']);
                $this->newLine();
                $this->warn('Initial tenant administrator:');
                $this->line('  email: '.$admin['email']);
                if (isset($admin['password']) && is_string($admin['password'])) {
                    $this->line('  password: '.$admin['password']);
                } elseif (! empty($admin['hint'])) {
                    $this->comment('  '.$admin['hint']);
                }
                if (! empty($admin['password_generated'])) {
                    $this->comment('  (password was auto-generated for this tenant)');
                }
            }
        }

        return self::SUCCESS;
    }
}

