<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        private readonly TenantActivityLogger $activity,
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

        $fresh = $user->fresh(['roles']);
        $roleNames = $fresh->getRoleNames()->values()->all();
        $actor = Auth::user();
        $this->activity->record(
            module: 'team_access',
            action: 'rbac.user_created',
            summary: 'User created · '.$fresh->email,
            entityType: 'user',
            entityId: (string) $fresh->id,
            entityLabel: $fresh->email,
            actor: $actor instanceof TenantUser ? $actor : null,
            changes: WorkspaceAuditChanges::of([
                'roles' => [
                    'from' => null,
                    'to' => $roleNames,
                ],
                'status' => [
                    'from' => null,
                    'to' => 'active',
                ],
            ]),
        );

        return [
            'user' => $fresh,
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
            $passwordChanged = true;
        } else {
            $passwordChanged = false;
        }

        $user->save();

        $beforeRoles = $user->getRoleNames()->sort()->values()->all();
        if ($roles !== null) {
            $this->assertRolesExist($roles);
            $this->seatLimits->assertCanTransitionToRoles($user, $roles !== [] ? $roles : ['viewer']);
            $user->syncRoles($roles);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if ($passwordChanged) {
            $this->sessionService->revokeAllForUser((string) $user->id);
            $this->refreshTokenService->revokeAllForUser((string) $user->id);
            $user->tokens()->delete();
        }

        $fresh = $user->fresh(['roles']);
        $afterRoles = $fresh->getRoleNames()->sort()->values()->all();
        $changes = WorkspaceAuditChanges::of([
            'roles' => [
                'from' => $beforeRoles,
                'to' => $afterRoles,
            ],
        ]);
        if ($changes !== [] || $passwordChanged) {
            $actor = Auth::user();
            $this->activity->record(
                module: 'team_access',
                action: 'rbac.user_updated',
                summary: 'User updated · '.$fresh->email,
                entityType: 'user',
                entityId: (string) $fresh->id,
                entityLabel: $fresh->email,
                actor: $actor instanceof TenantUser ? $actor : null,
                metadata: $passwordChanged ? ['password_rotated' => true] : [],
                changes: $changes,
            );
        }

        return $fresh;
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

        $this->activity->record(
            module: 'team_access',
            action: 'rbac.user_deactivated',
            summary: 'User deactivated · '.$target->email,
            entityType: 'user',
            entityId: (string) $target->id,
            entityLabel: $target->email,
            actor: $actor,
            changes: WorkspaceAuditChanges::of([
                'status' => [
                    'from' => 'active',
                    'to' => 'inactive',
                ],
            ]),
        );
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

        $fresh = $target->fresh(['roles']);
        $actor = Auth::user();
        $this->activity->record(
            module: 'team_access',
            action: 'rbac.user_reactivated',
            summary: 'User reactivated · '.$fresh->email,
            entityType: 'user',
            entityId: (string) $fresh->id,
            entityLabel: $fresh->email,
            actor: $actor instanceof TenantUser ? $actor : null,
            changes: WorkspaceAuditChanges::of([
                'status' => [
                    'from' => 'inactive',
                    'to' => 'active',
                ],
            ]),
        );

        return $fresh;
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
            $currentRoles = $target->getRoleNames()->all();
            $nextRoles = $currentRoles;

            if ($mode === 'replace' && $roles !== []) {
                $nextRoles = $roles;
            } elseif ($roles !== []) {
                $nextRoles = array_values(array_unique(array_merge($currentRoles, $roles)));
            }

            if ($removeRoles !== []) {
                $nextRoles = array_values(array_diff($nextRoles, $removeRoles));
            }

            try {
                $this->seatLimits->assertCanTransitionToRoles(
                    $target,
                    $nextRoles !== [] ? $nextRoles : ['viewer'],
                );
            } catch (ValidationException $e) {
                $errors[] = [
                    'user_id' => (string) $target->id,
                    'message' => (string) collect($e->errors())->flatten()->first(),
                ];

                continue;
            }

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

    /**
     * @param  list<string>  $userIds
     * @return array{
     *   processed: int,
     *   skipped: int,
     *   errors: list<array{user_id: string, message: string}>,
     *   passwords: list<array{user_id: string, email: string, name: string, temporary_password: string}>
     * }
     */
    public function bulkResetPasswords(
        TenantUser $actor,
        array $userIds,
        ?string $sharedPassword = null,
        bool $revokeSessions = true,
    ): array {
        $processed = 0;
        $skipped = 0;
        $errors = [];
        $passwords = [];
        $shared = is_string($sharedPassword) && $sharedPassword !== '' ? $sharedPassword : null;
        $actorId = (string) $actor->id;
        $revokedUserIds = [];

        /** @var Collection<string, TenantUser> $targetsById */
        $targetsById = TenantUser::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy(static fn (TenantUser $user): string => (string) $user->id);

        foreach ($userIds as $userId) {
            $userId = (string) $userId;

            if ($actorId === $userId) {
                $errors[] = [
                    'user_id' => $userId,
                    'message' => (string) __('You cannot bulk-reset your own password.'),
                ];

                continue;
            }

            $target = $targetsById->get($userId);
            if (! $target instanceof TenantUser) {
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

            $plain = $shared ?? Str::password(16);
            $target->password = Hash::make($plain);
            $target->save();

            $this->auditService->log(
                'auth.admin.password_reset',
                (string) $target->id,
                null,
                [
                    'revoked_by' => $actorId,
                    'bulk' => true,
                    'sessions_revoked' => $revokeSessions,
                ],
                'medium',
            );

            $passwords[] = [
                'user_id' => (string) $target->id,
                'email' => (string) $target->email,
                'name' => (string) $target->name,
                'temporary_password' => $plain,
            ];
            $revokedUserIds[] = (string) $target->id;
            $processed++;
        }

        if ($revokeSessions && $revokedUserIds !== []) {
            $this->sessionService->revokeAllForUsers($revokedUserIds);
            $this->refreshTokenService->revokeAllForUsers($revokedUserIds);
            DB::connection('tenant')->table('personal_access_tokens')
                ->where('tokenable_type', TenantUser::class)
                ->whereIn('tokenable_id', $revokedUserIds)
                ->delete();
        }

        return compact('processed', 'skipped', 'errors', 'passwords');
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
     * @param  list<array{email: string, name: string, role?: string, roles?: string|list<string>}>  $rows
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function importRows(array $rows): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];
        $paidSeatErrors = 0;

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

            $roles = $this->parseImportRoles($row['roles'] ?? $row['role'] ?? 'viewer');
            if ($roles === []) {
                $roles = ['viewer'];
            }

            try {
                $this->seatLimits->assertCanAddActiveUser($roles);
                $this->create($name, $email, $roles);
                $created++;
            } catch (ValidationException $e) {
                $message = (string) collect($e->errors())->flatten()->first();
                if (str_contains(strtolower($message), 'seat limit')) {
                    $paidSeatErrors++;
                    if ($paidSeatErrors === 1) {
                        $errors[] = "Row {$line}: {$message}";
                    }

                    continue;
                }

                $errors[] = "Row {$line}: {$message}";
            }
        }

        if ($paidSeatErrors > 1) {
            $errors[] = __(
                ':count additional row(s) skipped because the paid seat limit was reached.',
                ['count' => $paidSeatErrors - 1],
            );
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * Accept a single role, comma/semicolon/pipe-separated roles, or a roles list.
     *
     * @param  mixed  $raw
     * @return list<string>
     */
    private function parseImportRoles(mixed $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $value = trim((string) $raw);
            if ($value === '') {
                return [];
            }
            $parts = preg_split('/[,;|]+/', $value) ?: [];
        }

        $roles = [];
        foreach ($parts as $part) {
            $role = strtolower(trim((string) $part));
            $role = str_replace(' ', '_', $role);
            if ($role === '') {
                continue;
            }
            $roles[] = $role;
        }

        return array_values(array_unique($roles));
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
