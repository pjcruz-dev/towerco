<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

/**
 * Creates the first tenant-scoped administrator after the tenant database exists.
 */
class TenantAdminBootstrapService
{
    public function __construct(
        private readonly TenantRbacBaselineService $rbacBaseline,
    ) {}

    /**
     * @return array{email: string, password: string, password_generated: bool}
     */
    public function bootstrap(Tenant $tenant, string $normalizedDomain): array
    {
        return $tenant->run(function () use ($normalizedDomain) {
            $this->rbacBaseline->ensure();

            $email = 'admin@'.$normalizedDomain;
            $name = (string) config('toweros.tenant_bootstrap_admin_name', 'Tenant administrator');

            $configured = config('toweros.tenant_bootstrap_admin_password');
            $plain = is_string($configured) && $configured !== ''
                ? $configured
                : Str::password(24);
            $passwordGenerated = ! is_string($configured) || $configured === '';

            $user = TenantUser::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $plain,
                    'password_login_exempt' => true,
                ],
            );

            $user->syncRoles(['tenant_admin']);

            return [
                'email' => $email,
                'password' => $plain,
                'password_generated' => $passwordGenerated,
            ];
        });
    }
}
