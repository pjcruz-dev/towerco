<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;

final class EApprovalNotificationDispatcher
{
    public function dispatchApprovalAssigned(EApprovalSubmission $submission, string $approverUserId): void
    {
        $user = TenantUser::query()->find($approverUserId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse($user, new EApprovalSubmissionNotification($submission, 'approval_assigned'));
    }

    public function dispatchToRequestor(EApprovalSubmission $submission, string $event, ?string $actorName = null): void
    {
        $submission->loadMissing('requestor');
        if ($submission->requestor === null) {
            return;
        }

        $this->sendAfterResponse(
            $submission->requestor,
            new EApprovalSubmissionNotification($submission, $event, $actorName),
        );
    }

    public function dispatchSlaReminder(EApprovalRequestApproval $approval): void
    {
        $approval->loadMissing('approver', 'submission');
        if ($approval->approver === null) {
            return;
        }

        $submission = $approval->submission;
        if ($submission === null) {
            return;
        }

        $this->sendAfterResponse(
            $approval->approver,
            new EApprovalSubmissionNotification($submission, 'sla_reminder'),
        );
    }

    /**
     * @param  list<string>  $recipientIds
     */
    public function dispatchSlaEscalation(EApprovalRequestApproval $approval, array $recipientIds): void
    {
        $approval->loadMissing('submission');
        $submission = $approval->submission;
        if ($submission === null) {
            return;
        }

        $users = TenantUser::query()->whereIn('id', $recipientIds)->get();
        foreach ($users as $user) {
            $this->sendAfterResponse($user, new EApprovalSubmissionNotification($submission, 'sla_escalation'));
        }
    }

    /**
     * Send approval emails after the HTTP response so submit/create APIs return quickly
     * (sync queue + ShouldQueue notifications otherwise block the browser until mail finishes).
     */
    private function sendAfterResponse(TenantUser $user, EApprovalSubmissionNotification $notification): void
    {
        SafeMailNotificationSender::sendAfterResponse([$user], $notification);
    }
}
