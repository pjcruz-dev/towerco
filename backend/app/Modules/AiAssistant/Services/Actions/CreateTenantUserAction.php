<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Create one workspace user. The password stays in the proposal payload and is not repeated in the reply.
 */
final class CreateTenantUserAction implements AssistantActionInterface
{
    public function __construct(
        private readonly TenantUserAdminService $users,
    ) {}

    public function name(): string
    {
        return 'create_tenant_user';
    }

    public function description(): string
    {
        return 'Create one workspace user by name, email, role, and password. Does not deactivate anyone.';
    }

    public function requiredModule(): ?string
    {
        return 'team_access';
    }

    public function requiredDomainPermissions(): array
    {
        return ['user:manage'];
    }

    public function plannerArgumentRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['sometimes'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function argumentRules(): array
    {
        return $this->plannerArgumentRules();
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $email = $this->emailFrom($args, $question);
        $name = $this->stringFrom($args, 'name') ?? $this->named($question);
        $password = $this->stringFrom($args, 'password') ?? $this->afterWord($question, 'password');
        $roles = $this->rolesFrom($args, $question);

        if ($email === null || $name === null || $password === null) {
            throw ValidationException::withMessages([
                'user' => ['Include the name, email, and password for the user you want to create.'],
            ]);
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Create user',
            summary: sprintf('%s will be created as %s.', $email, implode(', ', $roles)),
            payload: [
                'name' => $name,
                'email' => $email,
                'roles' => $roles,
                'password' => $password,
            ],
            preview: [
                'name' => $name,
                'email' => $email,
                'roles' => implode(', ', $roles),
            ],
            moduleKey: 'team_access',
            confirmLabel: 'Create user',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        unset($viewer);
        $roles = $payload['roles'] ?? ['viewer'];
        if (is_string($roles)) {
            $roles = array_values(array_filter(array_map('trim', explode(',', $roles))));
        }
        if (! is_array($roles) || $roles === []) {
            $roles = ['viewer'];
        }

        $created = $this->users->create(
            (string) $payload['name'],
            (string) $payload['email'],
            array_map(static fn (mixed $role): string => (string) $role, $roles),
            (string) $payload['password'],
        );
        $user = $created['user'];

        return new ActionExecutionResult(
            ok: true,
            entityType: 'user',
            entityId: (string) $user->id,
            entityLabel: sprintf('%s was created and is active.', (string) $user->email),
            meta: [
                'email' => (string) $user->email,
                'roles' => $user->getRoleNames()->values()->all(),
            ],
            href: '/admin/users',
        );
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function emailFrom(array $args, string $question): ?string
    {
        $raw = $this->stringFrom($args, 'email');
        if ($raw !== null) {
            return TenantUser::normalizeEmail($raw);
        }
        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $question, $match) !== 1) {
            return null;
        }

        return TenantUser::normalizeEmail($match[0]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<string>
     */
    private function rolesFrom(array $args, string $question): array
    {
        $raw = $args['roles'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }
        if (is_array($raw) && $raw !== []) {
            return array_values(array_map(static fn (mixed $role): string => trim((string) $role), $raw));
        }
        if (preg_match('/\bas\s+(tenant_admin|manager|viewer)\b/i', $question, $match) === 1) {
            return [strtolower($match[1])];
        }

        return ['viewer'];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function stringFrom(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function named(string $question): ?string
    {
        if (preg_match('/\bnamed\s+(.+?)\s+password\b/iu', $question, $match) !== 1) {
            return null;
        }

        $name = trim($match[1]);

        return $name === '' ? null : $name;
    }

    private function afterWord(string $question, string $word): ?string
    {
        if (preg_match('/\b'.preg_quote($word, '/').'\s+(\S+)/iu', $question, $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }
}
