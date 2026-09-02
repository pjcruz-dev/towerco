<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use App\Modules\Ticketing\Support\TicketingNotificationCategory;

final class TenantNotificationCategoryResolver
{
    public static function for(string $module, string $type): string
    {
        return match ($module) {
            TenantNotificationModule::E_APPROVAL => EApprovalNotificationCategory::forType($type),
            TenantNotificationModule::TICKETING => TicketingNotificationCategory::forType($type),
            default => 'update',
        };
    }

    public static function hrefFor(
        string $module,
        string $type,
        ?string $subjectType,
        ?string $subjectId,
        ?string $hrefOverride = null,
    ): string {
        if ($hrefOverride !== null && $hrefOverride !== '') {
            return $hrefOverride;
        }

        return match ($module) {
            TenantNotificationModule::E_APPROVAL => EApprovalNotificationCategory::hrefFor(
                $type,
                $subjectType === 'submission' ? $subjectId : null,
            ),
            TenantNotificationModule::TICKETING => TicketingNotificationCategory::hrefFor(
                $subjectType === 'ticket' ? $subjectId : null,
            ),
            default => '/dashboard',
        };
    }
}
