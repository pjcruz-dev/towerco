<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class EApprovalApprovalRerouteService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
    ) {}

    public function reroute(
        EApprovalRequestApproval $approval,
        string $newApproverId,
        string $reason,
        TenantUser $actor,
    ): EApprovalRequestApproval {
        if (! $actor->can('e_approval:forms:manage')) {
            throw ValidationException::withMessages([
                'approval' => [__('Only E-Approval administrators can reroute approvals.')],
            ]);
        }

        $reason = trim($reason);
        if (strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'reason' => [__('A reroute reason is required (min 5 characters).')],
            ]);
        }

        if ($approval->status !== EApprovalApprovalStatus::PENDING) {
            throw ValidationException::withMessages([
                'approval' => [__('Only pending approvals can be rerouted.')],
            ]);
        }

        if ((string) $approval->approver_id === $newApproverId) {
            throw ValidationException::withMessages([
                'new_approver_id' => [__('Approval is already assigned to that user.')],
            ]);
        }

        $target = TenantUser::query()->where('id', $newApproverId)->where('is_active', true)->first();
        if ($target === null) {
            throw ValidationException::withMessages([
                'new_approver_id' => [__('Target approver was not found.')],
            ]);
        }

        $previousApproverId = (string) $approval->approver_id;
        $approval->approver_id = $newApproverId;
        $approval->last_reminder_at = null;
        $approval->escalated_at = null;
        $approval->save();

        $approval->loadMissing('submission');
        $submission = $approval->submission;

        if ($submission !== null) {
            $this->audit->log(
                'approval_rerouted',
                $submission->id,
                "From {$previousApproverId} to {$newApproverId}: {$reason}",
                $actor,
            );
            $this->inApp->notify(
                $newApproverId,
                'approval_assigned',
                $submission->id,
                __('You were assigned approval for :doc (rerouted).', ['doc' => $submission->document_no]),
                submission: $submission,
                actor: $actor,
            );
            $this->mail->dispatchApprovalAssigned($submission, $newApproverId);

            if ($previousApproverId !== '') {
                $this->inApp->notify(
                    $previousApproverId,
                    'approval_rerouted',
                    $submission->id,
                    __('Approval for :doc was rerouted to another approver.', ['doc' => $submission->document_no]),
                    submission: $submission,
                    actor: $actor,
                );
            }
        }

        return $approval->fresh(['approver', 'step', 'submission']);
    }
}
