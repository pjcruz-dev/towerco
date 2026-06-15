<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFinanceProcurementPolicyService;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSettingsShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSettingsService $settings,
        EApprovalFinanceProcurementPolicyService $procurementPolicy,
    ): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $mailer = (string) config('toweros.notifications_mail_mailer', config('mail.default'));

        return $this->ok([
            'sla_reminder_minutes' => $settings->getInt(EApprovalSettingsService::SLA_REMINDER_MINUTES, 2880),
            'sla_escalation_minutes' => $settings->getInt(EApprovalSettingsService::SLA_ESCALATION_MINUTES, 4320),
            'manual_follow_up_cooldown_minutes' => $settings->getInt(EApprovalSettingsService::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES, 720),
            'feature_delegation_ui' => $settings->getString(EApprovalSettingsService::FEATURE_DELEGATION_UI, 'false'),
            'provision_manager_users' => $settings->provisionManagerUsers() ? 'true' : 'false',
            'notifications_mailer' => $mailer,
            'notifications_mailer_ready' => $mailer !== 'log' && $mailer !== 'array',
            'finance_procurement_policy' => $procurementPolicy->snapshot(),
            ...$procurementPolicy->snapshot(),
        ]);
    }
}
