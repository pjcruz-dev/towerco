<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalDelegation;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;

final class EApprovalSlaRunnerService
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalDelegationService $delegations,
    ) {}

    /**
     * @return array{reminders: int, escalations: int}
     */
    public function run(): array
    {
        $reminderMinutes = $this->settings->getInt(EApprovalSettingsService::SLA_REMINDER_MINUTES, 48 * 60);
        $escalationMinutes = $this->settings->getInt(EApprovalSettingsService::SLA_ESCALATION_MINUTES, 72 * 60);
        $reminderRepeat = max(1, $reminderMinutes);

        $reminders = 0;
        $escalations = 0;

        $overdue = EApprovalRequestApproval::query()
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->where('created_at', '<=', now()->subMinutes($reminderMinutes))
            ->where(function ($q) use ($reminderRepeat): void {
                $q->whereNull('last_reminder_at')
                    ->orWhere('last_reminder_at', '<=', now()->subMinutes($reminderRepeat));
            })
            ->get();

        foreach ($overdue as $approval) {
            if ($approval->approver_id === null) {
                continue;
            }

            $approval->loadMissing('submission.form');
            $submission = $approval->submission;

            $this->mail->dispatchSlaReminder($approval);
            $this->inApp->notify(
                (string) $approval->approver_id,
                'sla_reminder',
                (string) $approval->submission_id,
                $submission !== null
                    ? __('Reminder: approval pending for :doc.', ['doc' => $submission->document_no])
                    : __('Reminder: approval pending beyond SLA threshold.'),
                submission: $submission,
            );
            $approval->last_reminder_at = now();
            $approval->save();
            $this->audit->log('sla_reminder_sent', (string) $approval->submission_id, "Approver {$approval->approver_id}");
            $reminders++;
        }

        $escalationTargets = EApprovalRequestApproval::query()
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->where('created_at', '<=', now()->subMinutes($escalationMinutes))
            ->whereNull('escalated_at')
            ->get();

        foreach ($escalationTargets as $approval) {
            if ($approval->approver_id === null) {
                continue;
            }

            $recipientIds = [(string) $approval->approver_id];
            $today = Carbon::today();
            $delegateIds = EApprovalDelegation::query()
                ->where('delegator_id', $approval->approver_id)
                ->where('is_active', true)
                ->whereDate('valid_from', '<=', $today)
                ->where(function ($q) use ($today): void {
                    $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
                })
                ->pluck('delegate_id')
                ->map(static fn ($id) => (string) $id)
                ->all();
            $recipientIds = array_merge($recipientIds, $delegateIds);

            $admins = TenantUser::query()
                ->where('is_active', true)
                ->whereHas('roles', static fn ($q) => $q->whereIn('name', ['tenant_admin', 'e_approval_admin']))
                ->pluck('id')
                ->map(static fn ($id) => (string) $id)
                ->all();

            $recipientIds = array_values(array_unique(array_merge($recipientIds, $admins)));

            $approval->loadMissing('submission.form');
            $submission = $approval->submission;

            foreach ($recipientIds as $recipientId) {
                $this->inApp->notify(
                    $recipientId,
                    'sla_escalation',
                    (string) $approval->submission_id,
                    $submission !== null
                        ? __('Escalation: approval pending for :doc.', ['doc' => $submission->document_no])
                        : __('Escalation: approval pending beyond escalation threshold.'),
                    submission: $submission,
                );
            }

            $this->mail->dispatchSlaEscalation($approval, $recipientIds);
            $approval->escalated_at = now();
            $approval->save();
            $this->audit->log('sla_escalation_sent', (string) $approval->submission_id, "Approver {$approval->approver_id}");
            $escalations++;
        }

        $this->settings->setString('sla_last_check_at', now()->toIso8601String());

        return ['reminders' => $reminders, 'escalations' => $escalations];
    }
}
