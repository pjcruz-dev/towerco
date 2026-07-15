<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Documents\Support\DocumentsNotificationCategory;
use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use App\Modules\ProcurementOne\Support\ProcurementNotificationCategory;
use App\Modules\Ticketing\Support\TicketingNotificationCategory;

final class TenantNotificationCategoryResolver
{
    public static function for(string $module, string $type): string
    {
        return match ($module) {
            TenantNotificationModule::E_APPROVAL => EApprovalNotificationCategory::forType($type),
            TenantNotificationModule::PROJECT_ONE => ProjectOneNotificationCategory::forGateEvent(
                str_starts_with($type, 'gate_') ? substr($type, 5) : $type,
            ),
            TenantNotificationModule::TICKETING => TicketingNotificationCategory::forType($type),
            TenantNotificationModule::DOCUMENTS => DocumentsNotificationCategory::forType($type),
            TenantNotificationModule::PROCUREMENT_ONE => ProcurementNotificationCategory::forType($type),
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
            TenantNotificationModule::PROJECT_ONE => ProjectOneNotificationCategory::hrefFor(
                $type,
                $subjectType === 'gate_approval_request' ? $subjectId : null,
                $subjectType === 'rollout_program' ? $subjectId : null,
            ),
            TenantNotificationModule::TICKETING => TicketingNotificationCategory::hrefFor(
                $subjectType === 'ticket' ? $subjectId : null,
            ),
            TenantNotificationModule::DOCUMENTS => DocumentsNotificationCategory::hrefFor(
                $subjectId,
                $subjectType === 'document_site' ? $subjectId : null,
            ),
            TenantNotificationModule::PROCUREMENT_ONE => ProcurementNotificationCategory::hrefFor(
                $subjectType === 'rfq' ? $subjectId : null,
            ),
            default => '/dashboard',
        };
    }
}
