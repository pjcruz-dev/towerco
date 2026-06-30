<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantUserProvisioningService
{
    public function __construct(
        private readonly TenantAuthPolicyService $authPolicy,
    ) {}

    /**
     * Resolve an existing user for Microsoft SSO, optionally creating one when auto-provision is enabled.
     */
    public function findForSso(string $tenantId, string $email, ?string $name = null): TenantUser
    {
        $email = TenantUser::normalizeEmail($email);
        $this->authPolicy->assertEmailDomainAllowed($tenantId, $email);

        $user = TenantUser::findByEmail($email);
        if ($user !== null) {
            return $user;
        }

        if (! $this->authPolicy->shouldAutoProvisionSso($tenantId)) {
            throw ValidationException::withMessages([
                'email' => [__('Your account is not provisioned for this organization. Contact your administrator.')],
            ]);
        }

        return $this->provision($email, $name);
    }

    private function provision(string $email, ?string $name): TenantUser
    {
        $email = TenantUser::normalizeEmail($email);

        /** @var TenantUser $provisioned */
        $provisioned = TenantUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name ?: strtok($email, '@') ?: 'User',
            'email' => $email,
            'password' => Str::password(32),
            'is_active' => true,
            'deactivated_at' => null,
            'password_login_exempt' => false,
        ]);

        $defaultRoles = config('toweros.tenant_auth.default_sso_roles', [
            'e_approval_requestor',
            'ticketing_contributor',
        ]);

        if ($defaultRoles !== []) {
            $provisioned->assignRole($defaultRoles);
        }

        return $provisioned;
    }
}
