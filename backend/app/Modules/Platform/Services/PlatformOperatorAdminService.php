<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Support\PlatformRoleCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PlatformOperatorAdminService
{
    public function __construct(
        private readonly PlatformRoleCatalog $roles,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, platform_role?: string|null}  $data
     */
    public function create(array $data): User
    {
        $email = strtolower(trim($data['email']));
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => [__('An account with this email already exists.')],
            ]);
        }

        $user = User::query()->create([
            'name' => trim($data['name']),
            'email' => $email,
            'password' => Hash::make($data['password']),
            'is_platform_admin' => true,
            'platform_role' => $this->roles->normalizeRole($data['platform_role'] ?? null),
        ]);

        return $user;
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, platform_role?: string|null}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        if (! $user->isPlatformAdmin()) {
            throw ValidationException::withMessages([
                'user' => [__('This user is not a platform operator.')],
            ]);
        }

        if (array_key_exists('name', $data)) {
            $user->name = trim((string) $data['name']);
        }

        if (array_key_exists('email', $data)) {
            $email = strtolower(trim((string) $data['email']));
            $exists = User::query()
                ->where('email', $email)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => [__('An account with this email already exists.')],
                ]);
            }
            $user->email = $email;
        }

        if (array_key_exists('platform_role', $data)) {
            $nextRole = $this->roles->normalizeRole($data['platform_role']);
            if ((string) $actor->id === (string) $user->id && $nextRole !== PlatformRoleCatalog::ROLE_SUPERADMIN) {
                throw ValidationException::withMessages([
                    'platform_role' => [__('You cannot remove your own superadmin role.')],
                ]);
            }
            $user->platform_role = $nextRole;
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make((string) $data['password']);
        }

        $user->save();

        return $user;
    }

    public function delete(User $user, User $actor): void
    {
        if (! $user->isPlatformAdmin()) {
            throw ValidationException::withMessages([
                'user' => [__('This user is not a platform operator.')],
            ]);
        }

        if ((string) $actor->id === (string) $user->id) {
            throw ValidationException::withMessages([
                'user' => [__('You cannot delete your own operator account.')],
            ]);
        }

        if ($user->resolvePlatformRole() === PlatformRoleCatalog::ROLE_SUPERADMIN) {
            $superadminCount = User::query()
                ->where('is_platform_admin', true)
                ->where(function ($query): void {
                    $query->where('platform_role', PlatformRoleCatalog::ROLE_SUPERADMIN)
                        ->orWhereNull('platform_role');
                })
                ->count();

            if ($superadminCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => [__('At least one superadmin operator must remain.')],
                ]);
            }
        }

        $user->tokens()->delete();
        $user->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'platform_role' => $user->resolvePlatformRole(),
            'platform_permissions' => $user->platformPermissions(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
