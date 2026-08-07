<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalExternalSubmissionNotification;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\EApproval\Support\EApprovalExternalMailEvent;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use Illuminate\Support\Facades\Notification;

final class EApprovalNotificationDispatcher
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    public function dispatchApprovalAssigned(
        EApprovalSubmission $submission,
        string $approverUserId,
        bool $revised = false,
    ): void {
        $user = TenantUser::query()->find($approverUserId);
        if ($user === null) {
            return;
        }

        $event = $revised ? 'approval_assigned_revised' : 'approval_assigned';
        $this->sendAfterResponse($user, new EApprovalSubmissionNotification($submission, $event));
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

    /**
     * Opt-in mail to the anonymous external submitter. Does not affect tenant-user mail.
     *
     * @param  list<array{file_name: string, url: string}>  $packageLinks
     */
    public function dispatchToExternalSubmitter(
        EApprovalSubmission $submission,
        string $event,
        ?string $actorName = null,
        ?string $detail = null,
        ?string $reviseUrl = null,
        array $packageLinks = [],
        ?string $packageNote = null,
    ): void {
        if (! $submission->isExternalSubmission()) {
            return;
        }

        $email = trim((string) $submission->external_submitter_email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $enabled = match ($event) {
            EApprovalExternalMailEvent::RECEIVED => $this->settings->notifyExternalOnReceived(),
            EApprovalExternalMailEvent::APPROVED => $this->settings->notifyExternalOnApproved(),
            EApprovalExternalMailEvent::REJECTED => $this->settings->notifyExternalOnRejected(),
            EApprovalExternalMailEvent::RETURNED => $this->settings->notifyExternalOnReturned(),
            default => false,
        };

        if (! $enabled) {
            return;
        }

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $email)],
            new EApprovalExternalSubmissionNotification(
                $submission,
                $event,
                $actorName,
                $detail,
                $reviseUrl,
                $packageLinks,
                $packageNote,
            ),
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

    public function dispatchManualFollowUp(
        EApprovalSubmission $submission,
        string $approverUserId,
        string $requestorName,
    ): void {
        $user = TenantUser::query()->find($approverUserId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse(
            $user,
            new EApprovalSubmissionNotification($submission, 'manual_follow_up', $requestorName),
        );
    }

    public function dispatchCancelled(EApprovalSubmission $submission, string $recipientUserId, ?string $actorName = null): void
    {
        $user = TenantUser::query()->find($recipientUserId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse(
            $user,
            new EApprovalSubmissionNotification($submission, 'cancelled', $actorName),
        );
    }

    public function dispatchApprovalNoLongerNeeded(EApprovalSubmission $submission, string $approverUserId): void
    {
        $user = TenantUser::query()->find($approverUserId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse(
            $user,
            new EApprovalSubmissionNotification($submission, 'approval_no_longer_needed'),
        );
    }

    public function dispatchApprovalRerouted(
        EApprovalSubmission $submission,
        string $previousApproverId,
        ?string $actorName = null,
        ?string $reason = null,
    ): void {
        $user = TenantUser::query()->find($previousApproverId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse(
            $user,
            new EApprovalSubmissionNotification(
                $submission,
                'approval_rerouted',
                $actorName,
                detail: $reason,
            ),
        );
    }

    public function dispatchWorkflowStepsSkipped(
        EApprovalSubmission $submission,
        string $detail,
    ): void {
        $submission->loadMissing('requestor');
        if ($submission->requestor === null) {
            return;
        }

        $this->sendAfterResponse(
            $submission->requestor,
            new EApprovalSubmissionNotification(
                $submission,
                'workflow_steps_skipped',
                detail: $detail,
            ),
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
