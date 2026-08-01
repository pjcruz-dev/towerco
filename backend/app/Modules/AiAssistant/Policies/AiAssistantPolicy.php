<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Policies;

use App\Modules\Identity\Models\TenantUser;

/**
 * Capability checks for the tenant AI Assistant module.
 *
 * Controllers also call $user->can(...) directly (TowerOS convention); this policy
 * centralizes the same checks for future conversation/knowledge authorization.
 */
final class AiAssistantPolicy
{
    public function ask(TenantUser $user): bool
    {
        return $user->can('ai_assistant:use');
    }

    public function manageKnowledge(TenantUser $user): bool
    {
        return $user->can('ai_assistant:knowledge:manage');
    }

    public function auditConversations(TenantUser $user): bool
    {
        return $user->can('ai_assistant:conversations:audit');
    }

    public function viewConversation(TenantUser $user, string $ownerUserId): bool
    {
        if ((string) $user->id === $ownerUserId) {
            return true;
        }

        return $this->auditConversations($user);
    }
}
