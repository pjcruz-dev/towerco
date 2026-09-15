<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Validation\ValidationException;

/**
 * Propose replacing a tenant user's roles (Confirm required). Used from Ask TowerOS Team & Access asks.
 */
final class UpdateUserRolesAction implements AssistantActionInterface
{
    public function __construct(
        private readonly TenantUserAdminService $users,
    ) {}

    public function name(): string
    {
        return 'update_user_roles';
    }

    public function description(): string
    {
        return 'Propose updating a workspace user\'s roles. Saves only after confirmation.';
    }

    public function requiredModule(): ?string
    {
        return 'team_access';
    }

    public function requiredDomainPermissions(): array
    {
        return ['user:manage'];
    }

    public function argumentRules(): array
    {
        // Roles may arrive as a comma-separated string from the confirm card inputs.
        return [
            'user_id' => ['required', 'uuid'],
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['required'],
            'replace' => ['sometimes'],
        ];
    }

    /**
     * Normalize confirm-card string fields before Laravel validation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizeConfirmPayload(array $payload): array
    {
        if (is_string($payload['roles'] ?? null)) {
            $roles = [];
            foreach (explode(',', (string) $payload['roles']) as $role) {
                $role = trim($role);
                if ($role !== '') {
                    $roles[] = $role;
                }
            }
            $payload['roles'] = $roles;
        }

        if (is_string($payload['replace'] ?? null)) {
            $raw = mb_strtolower(trim((string) $payload['replace']));
            $payload['replace'] = in_array($raw, ['1', 'true', 'yes', 'on'], true);
        }

        return $payload;
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $email = isset($args['email']) && is_string($args['email'])
            ? TenantUser::normalizeEmail(trim($args['email']))
            : $this->extractEmail($question);

        if ($email === null) {
            throw ValidationException::withMessages([
                'email' => ['Include the user email (e.g. name@company.com).'],
            ]);
        }

        $target = TenantUser::query()->where('email', $email)->first();
        if ($target === null) {
            throw ValidationException::withMessages([
                'email' => ["No workspace user found for {$email}."],
            ]);
        }

        $roles = [];
        if (is_array($args['roles'] ?? null)) {
            foreach ($args['roles'] as $role) {
                if (is_string($role) && trim($role) !== '') {
                    $roles[] = trim($role);
                }
            }
        }
        if ($roles === []) {
            $roles = $this->extractRoles($question);
        }
        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => ['Name the role to assign (e.g. viewer, manager, ticketing_viewer).'],
            ]);
        }

        $roles = array_values(array_unique($roles));
        $this->assertRolesExist($roles);

        $current = $target->getRoleNames()->sort()->values()->all();
        $replace = array_key_exists('replace', $args)
            ? (bool) $args['replace']
            : $this->wantsReplaceOnly($question);

        $payload = [
            'user_id' => (string) $target->id,
            'email' => (string) $target->email,
            'name' => (string) $target->name,
            'roles' => $roles,
            'replace' => $replace,
            'current_roles' => $current,
        ];

        $summary = $replace
            ? sprintf(
                'I can set **%s** (%s) to role%s **%s** only (replacing current: %s). Nothing changes until you confirm.',
                $target->name,
                $target->email,
                count($roles) === 1 ? '' : 's',
                implode(', ', $roles),
                $current !== [] ? implode(', ', $current) : 'none',
            )
            : sprintf(
                'I can add role%s **%s** to **%s** (%s). Current roles: %s. Nothing changes until you confirm.',
                count($roles) === 1 ? '' : 's',
                implode(', ', $roles),
                $target->name,
                $target->email,
                $current !== [] ? implode(', ', $current) : 'none',
            );

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Update user roles',
            summary: $summary,
            payload: $payload,
            preview: [
                'email' => $payload['email'],
                'name' => $payload['name'],
                'current_roles' => $current,
                'next_roles' => $roles,
                'mode' => $replace ? 'replace' : 'add',
            ],
            editableFields: [
                ['key' => 'email', 'label' => 'User email', 'type' => 'text', 'required' => true],
                ['key' => 'roles', 'label' => 'Roles (comma-separated)', 'type' => 'text', 'required' => true],
                ['key' => 'replace', 'label' => 'Replace all roles (1/0)', 'type' => 'text', 'required' => false],
            ],
            moduleKey: 'team_access',
            confirmLabel: 'Update roles',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $payload = self::normalizeConfirmPayload($payload);
        $userId = (string) ($payload['user_id'] ?? '');
        $email = isset($payload['email']) ? TenantUser::normalizeEmail((string) $payload['email']) : '';
        $roles = [];
        if (is_array($payload['roles'] ?? null)) {
            foreach ($payload['roles'] as $role) {
                if (is_string($role) && trim($role) !== '') {
                    $roles[] = trim($role);
                }
            }
        }

        $target = $userId !== ''
            ? TenantUser::query()->find($userId)
            : TenantUser::query()->where('email', $email)->first();

        if ($target === null) {
            throw ValidationException::withMessages(['email' => ['User not found.']]);
        }

        if ($roles === []) {
            throw ValidationException::withMessages(['roles' => ['At least one role is required.']]);
        }

        $this->assertRolesExist($roles);

        if ($this->isLastActiveTenantAdmin($target) && ! in_array(TenantRbacSystemRoles::FULL_ADMIN, $roles, true)) {
            throw ValidationException::withMessages([
                'roles' => ['Cannot remove tenant_admin from the last active tenant administrator.'],
            ]);
        }

        $updated = $this->users->update($target, null, null, $roles, null);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'tenant_user',
            entityId: (string) $updated->id,
            entityLabel: (string) $updated->email,
            meta: [
                'email' => (string) $updated->email,
                'roles' => $updated->getRoleNames()->values()->all(),
            ],
            href: '/users',
        );
    }

    private function extractEmail(string $question): ?string
    {
        if (preg_match('/([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i', $question, $m) !== 1) {
            return null;
        }

        return TenantUser::normalizeEmail($m[1]);
    }

    /**
     * @return list<string>
     */
    private function extractRoles(string $question): array
    {
        $q = mb_strtolower($question);
        $known = TenantRole::query()->where('guard_name', 'sanctum')->pluck('name')
            ->map(static fn ($n): string => (string) $n)
            ->all();

        $found = [];
        foreach ($known as $role) {
            $needle = mb_strtolower($role);
            if ($needle !== '' && preg_match('/\b'.preg_quote($needle, '/').'\b/u', $q) === 1) {
                $found[] = $role;
            }
        }

        // Prefer explicit "viewer only" over matching many roles mentioned in history.
        if (preg_match('/\bviewer\s+only\b|\bonly\s+viewer\b|\brole\s+to\s+viewer\b/', $q) === 1) {
            return ['viewer'];
        }

        return array_values(array_unique($found));
    }

    private function wantsReplaceOnly(string $question): bool
    {
        $q = mb_strtolower($question);

        return (bool) preg_match('/\b(only|replace|set\s+to|change\s+(?:the\s+)?role\s+to|make\s+(?:them|him|her)\s+)\b/', $q);
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
        if (! $user->hasRole(TenantRbacSystemRoles::FULL_ADMIN)) {
            return false;
        }

        return TenantUser::query()
            ->where('is_active', true)
            ->role(TenantRbacSystemRoles::FULL_ADMIN)
            ->where('id', '!=', $user->id)
            ->count() === 0;
    }
}
