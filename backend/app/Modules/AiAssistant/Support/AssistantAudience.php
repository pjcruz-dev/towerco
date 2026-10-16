<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\Identity\Models\TenantUser;

final class AssistantAudience
{
    public static function isTenantAdmin(mixed $user): bool
    {
        return $user instanceof TenantUser && $user->hasRole('tenant_admin');
    }
}
