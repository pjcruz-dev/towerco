<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

class TenantUserProvisioningService
{
    public function findOrProvision(string $email, ?string $name = null): TenantUser
    {
        /** @var TenantUser|null $user */
        $user = TenantUser::query()->where('email', $email)->first();
        if ($user) {
            return $user;
        }

        /** @var TenantUser $provisioned */
        $provisioned = TenantUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name ?: strtok($email, '@') ?: 'User',
            'email' => $email,
            'password' => Str::password(32),
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        $defaultRole = env('TENANT_SSO_DEFAULT_ROLE', 'viewer');
        $provisioned->assignRole($defaultRole);

        return $provisioned;
    }
}

