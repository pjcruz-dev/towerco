<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalExternalSubmissionNotification;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalExternalMailEvent;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
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
        $submission->loadMissing('form');
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (! EApprovalFormMailConfig::eventEnabled($metadata, 'approval_assigned')) {
            return;
        }

        $order = $this->pendingStepOrder($submission, $approverUserId);
        $presentation = EApprovalFormMailConfig::stepPresentation($submission, $order);
        $event = $revised ? 'approval_assigned_revised' : 'approval_assigned';

        if ($presentation['mail']['notify_assignee']) {
            $user = TenantUser::query()->find($approverUserId);
            if ($user !== null) {
                $this->sendAfterResponse($user, new EApprovalSubmissionNotification(
                    $submission,
                    $event,
                    stepOrder: $order,
                ));
            }
        }

        foreach ($presentation['mail']['cc_user_ids'] as $ccUserId) {
            if ($ccUserId === $approverUserId) {
                continue;
            }
            $cc = TenantUser::query()->find($ccUserId);
            if ($cc === null) {
                continue;
            }
            $this->sendAfterResponse($cc, new EApprovalSubmissionNotification(
                $submission,
                $event,
                stepOrder: $order,
            ));
        }
    }

    public function dispatchStepApproved(EApprovalSubmission $submission, int $stepOrder, ?string $actorName = null): void
    {
        $submission->loadMissing('form');
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (! EApprovalFormMailConfig::eventEnabled($metadata, 'step_approved')) {
            return;
        }

        $presentation = EApprovalFormMailConfig::stepPresentation($submission, $stepOrder);
        if (! $presentation['mail']['notify_requestor_on_approve']) {
            return;
        }

        $submission->loadMissing('requestor');
        if ($submission->requestor === null) {
            return;
        }

        $this->sendAfterResponse(
            $submission->requestor,
            new EApprovalSubmissionNotification(
                $submission,
                'step_approved',
                $actorName,
                stepOrder: $stepOrder,
            ),
        );
    }

    public function dispatchToRequestor(EApprovalSubmission $submission, string $event, ?string $actorName = null): void
    {
        $submission->loadMissing(['requestor', 'form']);
        if ($submission->requestor === null) {
            return;
        }

        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (! EApprovalFormMailConfig::eventEnabled($metadata, $event)) {
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
        bool $review = false,
    ): void {
        $user = TenantUser::query()->find($approverUserId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse(
            $user,
            new EApprovalSubmissionNotification(
                $submission,
                'manual_follow_up',
                $requestorName,
                null,
                $review ? 'review' : null,
            ),
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

    public function dispatchPaymentProof(
        EApprovalSubmission $submission,
        TenantUser $actor,
        string $status,
        string $comment,
    ): void {
        $requestorId = (string) $submission->requestor_id;
        if ($requestorId === '') {
            return;
        }

        $this->sendEventToUsers(
            $submission,
            [$requestorId],
            $status === 'hold' ? 'payment_held' : 'payment_released',
            $actor->name,
            $comment !== '' ? $comment : null,
        );
    }

    public function dispatchReviewed(EApprovalSubmission $submission, TenantUser $actor, ?string $note, int $stepOrder): void
    {
        $actorId = (string) $actor->id;
        $recipientIds = [];
        $requestorId = (string) $submission->requestor_id;
        if ($requestorId !== '' && $requestorId !== $actorId) {
            $recipientIds[] = $requestorId;
        }

        $peers = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->whereIn('status', [EApprovalApprovalStatus::PENDING, EApprovalApprovalStatus::ACKNOWLEDGED])
            ->whereNotNull('approver_id')
            ->whereHas('step', static fn ($query) => $query->where('step_order', $stepOrder))
            ->pluck('approver_id');

        foreach ($peers as $peerId) {
            $peerId = (string) $peerId;
            if ($peerId !== '' && $peerId !== $actorId) {
                $recipientIds[] = $peerId;
            }
        }

        $this->sendEventToUsers(
            $submission,
            array_values(array_unique($recipientIds)),
            'reviewed',
            $actor->name,
            $note,
            $stepOrder,
        );
    }

    public function dispatchComment(
        EApprovalSubmission $submission,
        string $recipientUserId,
        string $actorName,
        string $comment,
        bool $reply,
    ): void {
        $this->sendEventToUsers(
            $submission,
            [$recipientUserId],
            $reply ? 'comment_replied' : 'comment_added',
            $actorName,
            $comment,
        );
    }

    public function dispatchCommentMention(
        EApprovalSubmission $submission,
        string $recipientUserId,
        string $actorName,
        string $comment,
    ): void {
        $user = TenantUser::query()->find($recipientUserId);
        if ($user === null) {
            return;
        }

        $this->sendAfterResponse(
            $user,
            new EApprovalSubmissionNotification(
                $submission,
                'comment_mention',
                $actorName,
                detail: $comment,
            ),
        );
    }

    /**
     * Send approval emails after the HTTP response so submit/create APIs return quickly
     * (sync queue + ShouldQueue notifications otherwise block the browser until mail finishes).
     */
    private function pendingStepOrder(EApprovalSubmission $submission, string $approverUserId): int
    {
        $approval = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('approver_id', $approverUserId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with('step')
            ->first();

        $order = (int) ($approval?->step?->step_order ?? 0);

        return $order > 0 ? $order : max(1, (int) $submission->current_step);
    }

    /**
     * @param  list<string>  $userIds
     */
    private function sendEventToUsers(
        EApprovalSubmission $submission,
        array $userIds,
        string $event,
        ?string $actorName,
        ?string $detail = null,
        ?int $stepOrder = null,
    ): void {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (string $id): bool => $id !== '')));
        if ($userIds === []) {
            return;
        }

        $users = TenantUser::query()->whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            $this->sendAfterResponse($user, new EApprovalSubmissionNotification(
                $submission,
                $event,
                $actorName,
                detail: $detail !== null && trim($detail) !== '' ? $detail : null,
                stepOrder: $stepOrder,
            ));
        }
    }

    private function sendAfterResponse(TenantUser $user, EApprovalSubmissionNotification $notification): void
    {
        SafeMailNotificationSender::sendAfterResponse([$user], $notification);
    }
}
