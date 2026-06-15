<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central (landlord) operators: billing, tenant lifecycle, platform administration.
 * Authenticated via Passport on central domains; never stored in tenant databases.
 */
#[Fillable(['name', 'email', 'password', 'is_platform_admin', 'platform_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use CentralConnection;
    use HasApiTokens;
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUuids;
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'bool',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function resolvePlatformRole(): string
    {
        return app(\App\Modules\Platform\Support\PlatformRoleCatalog::class)
            ->normalizeRole($this->platform_role);
    }

    /**
     * @return list<string>
     */
    public function platformPermissions(): array
    {
        return app(\App\Modules\Platform\Support\PlatformRoleCatalog::class)
            ->permissionsForRole($this->resolvePlatformRole());
    }

    public function hasPlatformPermission(string $permission): bool
    {
        return in_array($permission, $this->platformPermissions(), true);
    }
}
