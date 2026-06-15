<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovalDecisionService
{
    public function __construct(
        private readonly SubmissionWorkflowService $workflow,
        private readonly EApprovalDelegationService $delegations,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalVendorRegistrationMasterDataService $vendorMasterData,
    ) {}

    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        ?string $status,
        bool $awaitingMeOnly,
    ): LengthAwarePaginator {
        $query = EApprovalRequestApproval::query()
            ->with(['submission.form', 'approver', 'step']);

        if ($awaitingMeOnly) {
            $query->where('approver_id', $viewer->id)->where('status', EApprovalApprovalStatus::PENDING);
        } elseif ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! $viewer->can('e_approval:forms:manage')) {
            $query->where('approver_id', $viewer->id);
        }

        $viewerId = (string) $viewer->id;
        $deduped = $query->orderByDesc('created_at')->get()->groupBy('submission_id')->map(
            function ($group) use ($viewerId, $awaitingMeOnly) {
                /** @var \Illuminate\Support\Collection<int, EApprovalRequestApproval> $group */
                $pending = $group->first(
                    static fn (EApprovalRequestApproval $a) => (string) $a->approver_id === $viewerId
                        && $a->status === EApprovalApprovalStatus::PENDING,
                );
                if ($pending !== null) {
                    return $pending;
                }

                if ($awaitingMeOnly) {
                    return null;
                }

                return $group->sortByDesc(
                    static fn (EApprovalRequestApproval $a) => (int) ($a->step?->step_order ?? 0),
                )->first();
            },
        )->filter()->values()->sortByDesc(
            static fn (EApprovalRequestApproval $a) => $a->created_at?->getTimestamp() ?? 0,
        )->values();

        $total = $deduped->count();
        $page = max(1, $page);
        $items = $deduped->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    public function decide(
        EApprovalRequestApproval $approval,
        string $decision,
        ?string $remarks,
        ?string $signature,
        TenantUser $actor,
    ): EApprovalRequestApproval {
        if ($approval->status !== EApprovalApprovalStatus::PENDING) {
            throw ValidationException::withMessages([
                'approval' => [__('This approval is no longer pending.')],
            ]);
        }

        $assignedId = (string) ($approval->approver_id ?? '');
        $canAct = $assignedId !== '' && $this->delegations->canActForApprover($actor, $assignedId);
        if (! $canAct && ! $actor->can('e_approval:forms:manage')) {
            throw ValidationException::withMessages([
                'approval' => [__('You are not assigned to this approval step.')],
            ]);
        }

        $approval->loadMissing(['submission', 'step']);
        $submission = $approval->submission;
        if ($submission === null) {
            throw ValidationException::withMessages(['approval' => [__('Submission not found.')]]);
        }

        if ($decision === 'rejected') {
            $remarks = trim((string) $remarks);
            if (strlen($remarks) < 5) {
                throw ValidationException::withMessages([
                    'remarks' => [__('Rejection remarks must be at least 5 characters.')],
                ]);
            }

            return $this->reject($approval, $submission, $remarks, $actor);
        }

        return $this->approve($approval, $submission, $remarks, $signature, $actor);
    }

    private function approve(
        EApprovalRequestApproval $approval,
        EApprovalSubmission $submission,
        ?string $remarks,
        ?string $signature,
        TenantUser $actor,
    ): EApprovalRequestApproval {
        return DB::connection('tenant')->transaction(function () use ($approval, $submission, $remarks, $signature, $actor) {
            $approval->status = EApprovalApprovalStatus::APPROVED;
            $approval->remarks = $remarks;
            $approval->signature = $signature;
            $approval->acted_at = now();
            $approval->save();

            if ($signature !== null && trim($signature) !== '') {
                $this->settings->setUserSignature((string) $actor->id, trim($signature));
            }

            $stepOrder = (int) ($approval->step?->step_order ?? $submission->current_step);
            $hasMore = $this->workflow->triggerNextStep($submission, $stepOrder);

            $submission->refresh();

            if (! $hasMore && $submission->status === EApprovalSubmissionStatus::PENDING) {
                $stillPending = $submission->approvals()->where('status', EApprovalApprovalStatus::PENDING)->exists();
                if (! $stillPending) {
                    $submission->status = EApprovalSubmissionStatus::APPROVED;
                    $submission->save();
                    $this->inApp->notify(
                        (string) $submission->requestor_id,
                        'approved',
                        $submission->id,
                        __('Your request :doc was approved.', ['doc' => $submission->document_no]),
                        submission: $submission,
                        actor: $actor,
                    );
                    $this->mail->dispatchToRequestor($submission, 'approved', $actor->name);
                    $this->audit->log('request_approved_final', $submission->id, null, $actor);
                    $submission->loadMissing(['form', 'values.field']);
                    $this->vendorMasterData->syncApprovedRegistration($submission, $actor);
                }
            } else {
                $this->audit->log('request_approved_step', $submission->id, "Step {$stepOrder}", $actor);
            }

            return $approval->fresh(['submission.form', 'approver', 'step']);
        });
    }

    private function reject(
        EApprovalRequestApproval $approval,
        EApprovalSubmission $submission,
        string $remarks,
        TenantUser $actor,
    ): EApprovalRequestApproval {
        return DB::connection('tenant')->transaction(function () use ($approval, $submission, $remarks, $actor) {
            $approval->status = EApprovalApprovalStatus::REJECTED;
            $approval->remarks = $remarks;
            $approval->acted_at = now();
            $approval->save();

            $submission->status = EApprovalSubmissionStatus::REJECTED;
            $submission->save();

            $this->inApp->notify(
                (string) $submission->requestor_id,
                'rejected',
                $submission->id,
                __('Your request :doc was rejected.', ['doc' => $submission->document_no]),
                submission: $submission,
                actor: $actor,
                bodyPreview: $remarks !== '' ? $remarks : null,
            );
            $this->mail->dispatchToRequestor($submission, 'rejected', $actor->name);
            $this->audit->log('request_rejected', $submission->id, $remarks, $actor);

            return $approval->fresh(['submission.form', 'approver', 'step']);
        });
    }
}
