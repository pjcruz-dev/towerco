<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\RefreshTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class TenantUserAdminService
{
    public function __construct(
        private readonly TenantSeatLimitService $seatLimits,
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuthAuditService $auditService,
    ) {}

    /**
     * @param  list<string>  $roles
     * @return array{user: TenantUser, generated_password: string|null}
     */
    public function create(string $name, string $email, array $roles, ?string $password = null): array
    {
        $this->seatLimits->assertCanAddActiveUser($roles);

        $email = TenantUser::normalizeEmail($email);
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => [__('A valid email address is required.')],
            ]);
        }

        if (TenantUser::emailExists($email)) {
            throw ValidationException::withMessages([
                'email' => [__('A user with this email already exists.')],
            ]);
        }

        $this->assertRolesExist($roles);

        $plain = is_string($password) && $password !== '' ? $password : Str::password(16);
        $generated = ! is_string($password) || $password === '';

        /** @var TenantUser $user */
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        $user->syncRoles($roles !== [] ? $roles : ['viewer']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'user' => $user->fresh(['roles']),
            'generated_password' => $generated ? $plain : null,
        ];
    }

    /**
     * @param  list<string>|null  $roles
     */
    public function update(TenantUser $user, ?string $name, ?string $email, ?array $roles, ?string $password): TenantUser
    {
        if ($name !== null) {
            $user->name = $name;
        }

        if ($email !== null) {
            $email = TenantUser::normalizeEmail($email);
            if ($email !== TenantUser::normalizeEmail((string) $user->email)) {
                if (TenantUser::emailExists($email, (string) $user->id)) {
                    throw ValidationException::withMessages([
                        'email' => [__('A user with this email already exists.')],
                    ]);
                }
                $user->email = $email;
            }
        }

        if (is_string($password) && $password !== '') {
            $user->password = Hash::make($password);
        }

        $user->save();

        if ($roles !== null) {
            $this->assertRolesExist($roles);
            $user->syncRoles($roles);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $user->fresh(['roles']);
    }

    public function deactivate(TenantUser $actor, TenantUser $target): void
    {
        if ((string) $actor->id === (string) $target->id) {
            throw ValidationException::withMessages([
                'user' => [__('You cannot deactivate your own account.')],
            ]);
        }

        if (! $target->isActive()) {
            return;
        }

        if ($this->isLastActiveTenantAdmin($target)) {
            throw ValidationException::withMessages([
                'user' => [__('At least one active tenant administrator must remain.')],
            ]);
        }

        $target->is_active = false;
        $target->deactivated_at = now();
        $target->save();
        $target->tokens()->delete();
    }

    public function revokeAllSessions(TenantUser $actor, TenantUser $target): void
    {
        $this->sessionService->revokeAllForUser((string) $target->id);
        $this->refreshTokenService->revokeAllForUser((string) $target->id);
        $target->tokens()->delete();

        $this->auditService->log(
            'auth.admin.sessions_revoked',
            (string) $target->id,
            null,
            ['revoked_by' => (string) $actor->id],
            'medium',
        );
    }

    public function reactivate(TenantUser $target): TenantUser
    {
        if ($target->isActive()) {
            return $target->fresh(['roles']);
        }

        $target->loadMissing('roles');
        $this->seatLimits->assertCanAddActiveUser($target->roles->pluck('name')->all());

        $target->is_active = true;
        $target->deactivated_at = null;
        $target->save();

        return $target->fresh(['roles']);
    }

    /**
     * @param  list<string>  $userIds
     * @return array{processed: int, skipped: int, errors: list<array{user_id: string, message: string}>}
     */
    public function bulkDeactivate(TenantUser $actor, array $userIds): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($userIds as $userId) {
            $target = TenantUser::query()->find($userId);
            if ($target === null) {
                $errors[] = [
                    'user_id' => $userId,
                    'message' => (string) __('User not found.'),
                ];

                continue;
            }

            if (! $target->isActive()) {
                $skipped++;

                continue;
            }

            try {
                $this->deactivate($actor, $target);
                $processed++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'user_id' => (string) $target->id,
                    'message' => (string) collect($e->errors())->flatten()->first(),
                ];
            }
        }

        return compact('processed', 'skipped', 'errors');
    }

    /**
     * @param  list<string>  $userIds
     * @return array{processed: int, skipped: int, errors: list<array{user_id: string, message: string}>}
     */
    public function bulkAssignRole(array $userIds, string $role): array
    {
        return $this->bulkAssignRoles($userIds, [$role], 'add', []);
    }

    /**
     * @param  list<string>  $userIds
     * @param  list<string>  $roles
     * @param  list<string>  $removeRoles
     * @return array{processed: int, skipped: int, errors: list<array{user_id: string, message: string}>}
     */
    public function bulkAssignRoles(
        array $userIds,
        array $roles,
        string $mode = 'add',
        array $removeRoles = [],
    ): array {
        $roles = array_values(array_unique(array_filter(array_map('trim', $roles))));
        $removeRoles = array_values(array_unique(array_filter(array_map('trim', $removeRoles))));

        if ($roles === [] && $removeRoles === []) {
            throw ValidationException::withMessages([
                'roles' => [__('At least one role to assign or remove is required.')],
            ]);
        }

        if ($roles !== []) {
            $this->assertRolesExist($roles);
        }

        if ($removeRoles !== []) {
            $this->assertRolesExist($removeRoles);
        }

        if (! in_array($mode, ['add', 'replace'], true)) {
            throw ValidationException::withMessages([
                'mode' => [__('Role assignment mode must be add or replace.')],
            ]);
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($userIds as $userId) {
            $target = TenantUser::query()->find($userId);
            if ($target === null) {
                $errors[] = [
                    'user_id' => $userId,
                    'message' => (string) __('User not found.'),
                ];

                continue;
            }

            if ($this->isLastActiveTenantAdmin($target) && $removeRoles !== []) {
                $remaining = array_values(array_diff($target->getRoleNames()->all(), $removeRoles));
                if ($remaining === [] || ! in_array('tenant_admin', $remaining, true)) {
                    $errors[] = [
                        'user_id' => (string) $target->id,
                        'message' => (string) __('Cannot remove roles from the last active tenant administrator.'),
                    ];

                    continue;
                }
            }

            $changed = false;

            if ($mode === 'replace' && $roles !== []) {
                if ($target->getRoleNames()->sort()->values()->all() !== collect($roles)->sort()->values()->all()) {
                    $target->syncRoles($roles);
                    $changed = true;
                }
            } elseif ($roles !== []) {
                foreach ($roles as $role) {
                    if ($target->hasRole($role)) {
                        continue;
                    }

                    $target->assignRole($role);
                    $changed = true;
                }
            }

            foreach ($removeRoles as $removeRole) {
                if (! $target->hasRole($removeRole)) {
                    continue;
                }

                if ($this->isLastActiveTenantAdmin($target) && $removeRole === 'tenant_admin') {
                    $errors[] = [
                        'user_id' => (string) $target->id,
                        'message' => (string) __('Cannot remove the tenant administrator role from the last active administrator.'),
                    ];

                    continue 2;
                }

                $target->removeRole($removeRole);
                $changed = true;
            }

            if ($changed) {
                $processed++;
            } else {
                $skipped++;
            }
        }

        if ($processed > 0) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return compact('processed', 'skipped', 'errors');
    }

    public function destroyPermanently(TenantUser $actor, TenantUser $target): void
    {
        if ((string) $actor->id === (string) $target->id) {
            throw ValidationException::withMessages([
                'user' => [__('You cannot delete your own account.')],
            ]);
        }

        if ($target->isActive()) {
            throw ValidationException::withMessages([
                'user' => [__('Deactivate the user before permanent deletion.')],
            ]);
        }

        $target->tokens()->delete();
        $target->delete();
    }

    /**
     * @return Collection<int, TenantUser>
     */
    public function allForExport(string $search, ?string $status): Collection
    {
        $query = TenantUser::query()
            ->with('roles:id,name')
            ->orderBy('name');

        $this->applyStatusFilter($query, $status);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query->get();
    }

    /**
     * @param  list<array{email: string, name: string, role?: string}>  $rows
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function importRows(array $rows): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $email = TenantUser::normalizeEmail((string) ($row['email'] ?? ''));
            $name = trim($row['name'] ?? '');

            if ($email === '' || $name === '') {
                $errors[] = "Row {$line}: email and name are required.";

                continue;
            }

            if (TenantUser::emailExists($email)) {
                $skipped++;

                continue;
            }

            if ($this->seatLimits->activeSeatCount() >= $this->seatLimits->seatLimit()) {
                $errors[] = "Row {$line}: ".__(
                    'Seat limit reached (:used / :limit).',
                    ['used' => $this->seatLimits->activeSeatCount(), 'limit' => $this->seatLimits->seatLimit()],
                );

                continue;
            }

            $role = trim((string) ($row['role'] ?? 'viewer'));
            if ($role === '') {
                $role = 'viewer';
            }

            try {
                $this->create($name, $email, [$role]);
                $created++;
            } catch (ValidationException $e) {
                $errors[] = "Row {$line}: ".collect($e->errors())->flatten()->first();
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertRolesExist(array $roles): void
    {
        foreach ($roles as $roleName) {
            if (! TenantRole::query()->where('name', $roleName)->where('guard_name', 'sanctum')->exists()) {
                throw ValidationException::withMessages([
                    'roles' => [__('Role :role does not exist.', ['role' => $roleName])],
                ]);
            }
        }
    }

    private function isLastActiveTenantAdmin(TenantUser $user): bool
    {
        if (! $user->hasRole('tenant_admin')) {
            return false;
        }

        return TenantUser::query()
            ->where('is_active', true)
            ->role('tenant_admin')
            ->where('id', '!=', $user->id)
            ->count() === 0;
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyStatusFilter($query, ?string $status): void
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }
}
