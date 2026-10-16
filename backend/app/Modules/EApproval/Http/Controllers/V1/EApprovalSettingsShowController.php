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
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $mailer = (string) config('toweros.notifications_mail_mailer', config('mail.default'));
        $policy = $procurementPolicy->snapshot();

        return $this->ok([
            'sla_reminder_minutes' => $settings->getInt(EApprovalSettingsService::SLA_REMINDER_MINUTES, 2880),
            'sla_escalation_minutes' => $settings->getInt(EApprovalSettingsService::SLA_ESCALATION_MINUTES, 4320),
            'sla_use_working_days' => $settings->getBool(EApprovalSettingsService::SLA_USE_WORKING_DAYS, true) ? 'true' : 'false',
            'manual_follow_up_cooldown_minutes' => $settings->getInt(EApprovalSettingsService::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES, 720),
            'feature_delegation_ui' => $settings->getString(EApprovalSettingsService::FEATURE_DELEGATION_UI, 'false'),
            'provision_manager_users' => $settings->provisionManagerUsers() ? 'true' : 'false',
            'notifications_mailer' => $mailer,
            'notifications_mailer_ready' => $mailer !== 'log' && $mailer !== 'array',
            'notify_external_on_received' => $settings->notifyExternalOnReceived() ? 'true' : 'false',
            'notify_external_on_approved' => $settings->notifyExternalOnApproved() ? 'true' : 'false',
            'notify_external_on_rejected' => $settings->notifyExternalOnRejected() ? 'true' : 'false',
            'notify_external_on_returned' => $settings->notifyExternalOnReturned() ? 'true' : 'false',
            'teams_webhook_url' => $settings->teamsWebhookUrl(),
            'notify_teams_on_external_submit' => $settings->notifyTeamsOnExternalSubmit() ? 'true' : 'false',
            'liquidation_requires_parent' => $policy['liquidation_requires_parent'] ? 'true' : 'false',
            'liquidation_overspend_mode' => $policy['liquidation_overspend_mode'],
            'liquidation_max_overspend_percent' => $policy['liquidation_max_overspend_percent'],
            'liquidation_due_days' => $policy['liquidation_due_days'],
            'liquidation_remind_days' => $policy['liquidation_remind_days'],
            'block_new_cash_advance' => $policy['block_new_cash_advance'] ? 'true' : 'false',
            'vat_rate_percent' => $policy['vat_rate_percent'],
            'vat_amount_includes_vat' => $policy['vat_amount_includes_vat'] ? 'true' : 'false',
            'per_diem_max_per_day' => $policy['per_diem_max_per_day'] ?? '',
            'hotel_accommodation_max' => $policy['hotel_accommodation_max'] ?? '',
            'site_search_scope' => $settings->siteSearchScope(),
        ]);
    }
}
