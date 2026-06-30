<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Documents\Services\ControlledDocumentEApprovalHookService;
use App\Modules\ProcurementOne\Services\ProcurementPrEApprovalHookService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
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
        private readonly ProcurementPrEApprovalHookService $procurementPrHook,
        private readonly ControlledDocumentEApprovalHookService $controlledDocumentHook,
    ) {}

    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        ?string $status,
        bool $awaitingMeOnly,
    ): LengthAwarePaginator {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $ranked = $this->rankedApprovalsQuery($viewer, $status, $awaitingMeOnly);
        $total = (clone $ranked)->count();

        $pageIds = (clone $ranked)
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->pluck('id')
            ->all();

        if ($pageIds === []) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            );
        }

        $order = array_flip($pageIds);
        $items = EApprovalRequestApproval::query()
            ->with(['submission.form', 'approver', 'step'])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(static fn (EApprovalRequestApproval $approval): int => $order[(string) $approval->id] ?? PHP_INT_MAX)
            ->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function rankedApprovalsQuery(
        TenantUser $viewer,
        ?string $status,
        bool $awaitingMeOnly,
    ): Builder {
        $viewerId = (string) $viewer->id;
        $pending = EApprovalApprovalStatus::PENDING;

        $inner = DB::connection('tenant')
            ->table('e_approval_request_approvals as era')
            ->leftJoin('e_approval_workflow_steps as steps', 'steps.id', '=', 'era.step_id');

        if ($awaitingMeOnly) {
            $inner->where('era.approver_id', $viewerId)->where('era.status', $pending);
        } elseif ($status !== null && $status !== 'all') {
            $inner->where('era.status', $status);
        }

        if (! $viewer->can('e_approval:forms:manage')) {
            $inner->where('era.approver_id', $viewerId);
        }

        $inner->selectRaw(
            'era.id, era.created_at, ROW_NUMBER() OVER (
                PARTITION BY era.submission_id
                ORDER BY
                    CASE WHEN era.approver_id = ? AND era.status = ? THEN 0 ELSE 1 END,
                    COALESCE(steps.step_order, 0) DESC,
                    era.created_at DESC
            ) AS approval_rank',
            [$viewerId, $pending],
        );

        return DB::connection('tenant')
            ->query()
            ->fromSub($inner, 'ranked_approvals')
            ->where('approval_rank', '=', 1);
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
                    $this->procurementPrHook->afterSubmissionMutation($submission, $actor);
                    $this->controlledDocumentHook->afterSubmissionMutation($submission, $actor);
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
            $submission->loadMissing(['form', 'values.field']);
            $this->procurementPrHook->afterSubmissionMutation($submission, $actor);

            return $approval->fresh(['submission.form', 'approver', 'step']);
        });
    }
}
