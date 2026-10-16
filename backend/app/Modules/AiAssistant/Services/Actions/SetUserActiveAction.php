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
 * Set one workspace user active or inactive. Inactive revokes their tokens.
 */
final class SetUserActiveAction implements AssistantActionInterface
{
    public function __construct(
        private readonly TenantUserAdminService $users,
    ) {}

    public function name(): string
    {
        return 'set_user_active';
    }

    public function description(): string
    {
        return 'Set a workspace user active or inactive by email. Inactive revokes their tokens. Does not delete the user.';
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
            'email' => ['required', 'email', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function argumentRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $email = $this->emailFrom($args, $question);
        if ($email === null) {
            throw ValidationException::withMessages([
                'email' => ['Include the email of the user you want to change.'],
            ]);
        }

        $active = $this->activeFrom($args, $question);
        if ($active === null) {
            throw ValidationException::withMessages([
                'active' => ['Say inactive or active.'],
            ]);
        }

        $target = TenantUser::query()->where('email', $email)->first();
        if (! $target instanceof TenantUser) {
            throw ValidationException::withMessages([
                'email' => [sprintf('No user matches %s.', $email)],
            ]);
        }

        $state = $active ? 'active' : 'inactive';

        return new ActionProposalDraft(
            action: $this->name(),
            title: $active ? 'Set user active' : 'Set user inactive',
            summary: sprintf('%s will be %s.', $email, $state),
            payload: [
                'email' => $email,
                'active' => $active,
            ],
            preview: [
                'email' => $email,
                'from' => $target->isActive() ? 'active' : 'inactive',
                'to' => $state,
            ],
            moduleKey: 'team_access',
            confirmLabel: $active ? 'Set active' : 'Set inactive',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $email = TenantUser::normalizeEmail((string) ($payload['email'] ?? ''));
        $active = filter_var($payload['active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            throw ValidationException::withMessages([
                'active' => ['Say inactive or active.'],
            ]);
        }

        $target = TenantUser::query()->where('email', $email)->first();
        if (! $target instanceof TenantUser) {
            throw ValidationException::withMessages([
                'email' => [sprintf('No user matches %s.', $email)],
            ]);
        }

        if ($active) {
            if ($target->isActive()) {
                $label = sprintf('%s is already active.', $email);
            } else {
                $this->users->reactivate($target);
                $label = sprintf('%s is now active.', $email);
            }
        } elseif (! $target->isActive()) {
            $label = sprintf('%s is already inactive.', $email);
        } else {
            $this->users->deactivate($viewer, $target);
            $label = sprintf('%s is now inactive.', $email);
        }

        $fresh = $target->fresh();

        return new ActionExecutionResult(
            ok: true,
            entityType: 'user',
            entityId: (string) $target->id,
            entityLabel: $label,
            meta: [
                'email' => $email,
                'is_active' => $fresh instanceof TenantUser ? $fresh->isActive() : $active,
            ],
            href: '/admin/users',
        );
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function emailFrom(array $args, string $question): ?string
    {
        $raw = $args['email'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return TenantUser::normalizeEmail($raw);
        }

        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $question, $match) !== 1) {
            return null;
        }

        return TenantUser::normalizeEmail($match[0]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function activeFrom(array $args, string $question): ?bool
    {
        if (array_key_exists('active', $args)) {
            $parsed = filter_var($args['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null || is_bool($args['active'])) {
                return is_bool($args['active']) ? $args['active'] : $parsed;
            }
        }

        $q = mb_strtolower($question);
        if (preg_match('/\b(inactive|deactivate|deactivated|disable|disabled)\b/u', $q) === 1) {
            return false;
        }
        if (preg_match('/\b(reactivate|activate|active|enable|enabled)\b/u', $q) === 1) {
            return true;
        }

        return null;
    }
}
