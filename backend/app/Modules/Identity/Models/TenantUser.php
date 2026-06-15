<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Database\Factories\TenantUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * End-user identity inside a tenant database (TowerCo staff, field users).
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'deactivated_at', 'password_login_exempt'])]
#[Hidden(['password', 'remember_token'])]
class TenantUser extends Authenticatable
{
    use HasApiTokens;
    /** @use HasFactory<TenantUserFactory> */
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use LogsActivity;
    use Notifiable;

    protected static function newFactory(): TenantUserFactory
    {
        return TenantUserFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected string $guard_name = 'sanctum';

    /**
     * Tenant-scoped models resolve on the stancl `tenant` connection after tenancy bootstrap.
     */
    protected $connection = 'tenant';

    /**
     * Tenant DB table is `users` (see tenant migrations). The `TenantUser` basename would otherwise resolve to `tenant_users`.
     */
    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'password_login_exempt' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function findByEmail(string $email): ?self
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }

        /** @var self|null $user */
        $user = self::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        return $user;
    }

    public static function emailExists(string $email, ?string $exceptUserId = null): bool
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return false;
        }

        $query = self::query()->whereRaw('LOWER(email) = ?', [$normalized]);
        if ($exceptUserId !== null && $exceptUserId !== '') {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('identity');
    }
}
