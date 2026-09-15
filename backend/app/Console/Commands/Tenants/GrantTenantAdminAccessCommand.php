<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

final class GrantTenantAdminAccessCommand extends Command
{
    protected $signature = 'tenants:grant-admin-access
        {--domain= : Tenant domain (e.g. staging.myapp.localhost)}
        {--email= : Tenant user email}
        {--roles=* : Roles to assign (default: tenant_admin, administrator, admin)}
    ';

    protected $description = 'Ensure RBAC baseline and grant full-access roles to a tenant user.';

    public function handle(TenantRbacBaselineService $rbac): int
    {
        $domain = trim((string) $this->option('domain'));
        $email = strtolower(trim((string) $this->option('email')));

        if ($domain === '' || $email === '') {
            $this->error('Both --domain and --email are required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->whereHas('domains', static fn ($q) => $q->where('domain', $domain))
            ->first();

        if ($tenant === null) {
            $this->error("No tenant found for domain {$domain}.");

            return self::FAILURE;
        }

        /** @var list<string> $roles */
        $roles = array_values(array_filter(
            array_map('strval', (array) $this->option('roles')),
            static fn (string $r): bool => $r !== '',
        ));
        if ($roles === []) {
            $roles = array_merge(
                [TenantRbacSystemRoles::FULL_ADMIN],
                TenantRbacSystemRoles::FULL_ACCESS_ALIASES,
            );
        }

        $tenant->run(function () use ($rbac, $email, $roles): void {
            $rbac->ensure();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $user = TenantUser::query()->where('email', $email)->first();
            if ($user === null) {
                throw new \RuntimeException("User not found: {$email}");
            }

            $user->syncRoles($roles);
            $user->forceFill(['is_active' => true])->save();
        });

        $this->info("Granted roles [".implode(', ', $roles)."] to {$email} on {$domain}.");

        return self::SUCCESS;
    }
}
