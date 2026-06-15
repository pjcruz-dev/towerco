<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;

final class TenantNotificationAccess
{
    /**
     * @return list<string>
     */
    public static function allowedModulesFor(?TenantUser $user): array
    {
        if ($user === null) {
            return [];
        }

        $enabled = app(TenantEnabledModulesResolver::class)->resolveForCurrentTenant();
        $modules = [];

        if (in_array('e_approval', $enabled, true) && $user->can('e_approval:view')) {
            $modules[] = TenantNotificationModule::E_APPROVAL;
        }

        if (
            in_array('project_one', $enabled, true)
            && ($user->can('project_one:rollout:view') || $user->can('project_one:rollout:gate:approve'))
        ) {
            $modules[] = TenantNotificationModule::PROJECT_ONE;
        }

        if (
            in_array('ticketing', $enabled, true)
            && ($user->can('ticketing:view') || $user->can('ticketing:tickets:manage'))
        ) {
            $modules[] = TenantNotificationModule::TICKETING;
        }

        return $modules;
    }

    public static function canAccessModule(?TenantUser $user, string $module): bool
    {
        return in_array($module, self::allowedModulesFor($user), true);
    }

    public static function abortUnlessCanAccessAny(?TenantUser $user): void
    {
        abort_unless(self::allowedModulesFor($user) !== [], 403);
    }
}
