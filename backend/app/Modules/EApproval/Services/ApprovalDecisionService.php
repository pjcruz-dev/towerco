<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Documents\Services\ControlledDocumentEApprovalHookService;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalExternalMailEvent;
use App\Modules\EApproval\Support\EApprovalReviewStep;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovalDecisionService
{
    private const SORTABLE = [
        'created_at',
        'status',
        'document_no',
    ];

    public function __construct(
        private readonly SubmissionWorkflowService $workflow,
        private readonly EApprovalDelegationService $delegations,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalSettingsService $settings,
        private readonly ControlledDocumentEApprovalHookService $controlledDocumentHook,
        private readonly EApprovalCommentService $comments,
        private readonly EApprovalExternalPackageService $externalPackages,
        private readonly EApprovalFinanceStageService $financeStage,
    ) {}

    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        ?string $status,
        bool $awaitingMeOnly,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $ranked = $this->rankedApprovalsQuery($viewer, $status, $awaitingMeOnly);
        $total = (clone $ranked)->count();

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'created_at:desc'),
            self::SORTABLE,
            'created_at',
            'desc',
        );

        $pageIds = (clone $ranked)
            ->orderBy($column, $direction)
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
            ->leftJoin('e_approval_workflow_steps as steps', 'steps.id', '=', 'era.step_id')
            ->leftJoin('e_approval_submissions as eas', 'eas.id', '=', 'era.submission_id');

        if ($awaitingMeOnly) {
            $inner->where('era.approver_id', $viewerId)->where('era.status', $pending);
        } elseif ($status !== null && $status !== 'all') {
            $inner->where('era.status', $status);
        }

        if (! $viewer->can('e_approval:forms:manage')) {
            $inner->where('era.approver_id', $viewerId);
        }

        $inner->selectRaw(
            'era.id, era.created_at, era.status, eas.document_no, ROW_NUMBER() OVER (
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

    /**
     * @param  array<string, mixed>  $financeInput
     * @param  list<string>  $fileRecipientIds
     */
    public function decide(
        EApprovalRequestApproval $approval,
        string $decision,
        ?string $remarks,
        ?string $signature,
        TenantUser $actor,
        array $financeInput = [],
        bool $proceedOptional = false,
        ?string $fileAudience = null,
        array $fileRecipientIds = [],
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

        if (in_array((string) $submission->status, [
            EApprovalSubmissionStatus::CANCELLED,
            EApprovalSubmissionStatus::APPROVED,
            EApprovalSubmissionStatus::REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'approval' => [__('This submission is no longer awaiting a decision.')],
            ]);
        }

        if (EApprovalReviewStep::isReviewStep($approval->step)) {
            if ($decision !== 'acknowledged') {
                throw ValidationException::withMessages([
                    'decision' => [__('Reviewers review the file. This step does not approve or reject the request.')],
                ]);
            }

            return $this->acknowledge($approval, $submission, $remarks, $signature, $actor, $proceedOptional, $fileAudience, $fileRecipientIds);
        }

        if ($decision === 'acknowledged') {
            throw ValidationException::withMessages([
                'decision' => [__('Only a reviewer step can be reviewed.')],
            ]);
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

        return $this->approve($approval, $submission, $remarks, $signature, $actor, $financeInput, $proceedOptional, $fileAudience, $fileRecipientIds);
    }

    private function acknowledge(
        EApprovalRequestApproval $approval,
        EApprovalSubmission $submission,
        ?string $remarks,
        ?string $signature,
        TenantUser $actor,
        bool $proceedOptional = false,
        ?string $fileAudience = null,
        array $fileRecipientIds = [],
    ): EApprovalRequestApproval {
        $signature = trim((string) $signature);
        if ($signature === '') {
            throw ValidationException::withMessages([
                'signature' => [__('Add your signature before reviewing.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($approval, $submission, $remarks, $signature, $actor, $proceedOptional, $fileAudience, $fileRecipientIds) {
            $this->rememberFileAudience($submission, $fileAudience, $fileRecipientIds);
            $previousApprovalStatus = (string) $approval->status;
            $note = trim((string) $remarks);

            $approval->status = EApprovalApprovalStatus::ACKNOWLEDGED;
            $approval->remarks = $note !== '' ? $note : null;
            $approval->signature = $signature;
            $approval->acted_at = now();
            $approval->save();

            $this->settings->setUserSignature((string) $actor->id, $signature);

            if ($note !== '') {
                $this->comments->add($submission, $note, $actor, notifyStakeholders: true);
            }

            $stepOrder = (int) ($approval->step?->step_order ?? $submission->current_step);
            $hasMore = $this->workflow->triggerNextStep($submission, $stepOrder, skipOptional: ! $proceedOptional);
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
                    $this->controlledDocumentHook->afterSubmissionMutation($submission, $actor);
                    app(EApprovalApprovedFileDistributionService::class)->send($submission);
                }
            }

            if ($submission->status !== EApprovalSubmissionStatus::APPROVED) {
                $this->mail->dispatchReviewed($submission, $actor, $note !== '' ? $note : null, $stepOrder);
            }

            $this->audit->log(
                'review_acknowledged',
                $submission->id,
                $note !== '' ? $note : 'Reviewed',
                $actor,
                WorkspaceAuditChanges::of([
                    'approval_status' => [
                        'from' => $previousApprovalStatus,
                        'to' => EApprovalApprovalStatus::ACKNOWLEDGED,
                    ],
                ]),
                entityLabel: $submission->document_no,
            );

            return $approval->fresh(['submission.form', 'approver', 'step']);
        });
    }

    /**
     * @param  list<string>  $recipientIds
     */
    private function rememberFileAudience(EApprovalSubmission $submission, ?string $audience, array $recipientIds = []): void
    {
        if (! in_array($audience, ['everyone', 'specific_person', 'selected_users'], true)) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => trim((string) $id), $recipientIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($audience === 'specific_person' && count($ids) !== 1) {
            throw ValidationException::withMessages([
                'file_recipient_ids' => [__('Choose one person to receive the files.')],
            ]);
        }

        if ($audience === 'selected_users' && $ids === []) {
            throw ValidationException::withMessages([
                'file_recipient_ids' => [__('Choose at least one person to receive the files.')],
            ]);
        }

        if ($audience === 'everyone') {
            $ids = [];
        }

        $decoded = [];
        $raw = $submission->workflow_snapshot_json;
        if (is_string($raw) && trim($raw) !== '') {
            try {
                $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $decoded = is_array($parsed) ? $parsed : [];
            } catch (\Throwable) {
                $decoded = [];
            }
        }

        $decoded['approved_file_audience'] = $audience;
        $decoded['approved_file_recipient_ids'] = $ids;
        $submission->workflow_snapshot_json = json_encode($decoded, JSON_THROW_ON_ERROR);
        $submission->save();
    }

    /**
     * @param  array<string, mixed>  $financeInput
     */
    private function approve(
        EApprovalRequestApproval $approval,
        EApprovalSubmission $submission,
        ?string $remarks,
        ?string $signature,
        TenantUser $actor,
        array $financeInput = [],
        bool $proceedOptional = false,
        ?string $fileAudience = null,
        array $fileRecipientIds = [],
    ): EApprovalRequestApproval {
        return DB::connection('tenant')->transaction(function () use ($approval, $submission, $remarks, $signature, $actor, $financeInput, $proceedOptional, $fileAudience, $fileRecipientIds) {
            $this->rememberFileAudience($submission, $fileAudience, $fileRecipientIds);
            $this->financeStage->captureOnApprove($submission, $approval->step, $financeInput);

            $previousApprovalStatus = (string) $approval->status;
            $previousSubmissionStatus = (string) $submission->status;

            $approval->status = EApprovalApprovalStatus::APPROVED;
            $approval->remarks = $remarks;
            $approval->signature = $signature;
            $approval->acted_at = now();
            $approval->save();

            if ($signature !== null && trim($signature) !== '') {
                $this->settings->setUserSignature((string) $actor->id, trim($signature));
            }

            $stepOrder = (int) ($approval->step?->step_order ?? $submission->current_step);
            $hasMore = $this->workflow->triggerNextStep($submission, $stepOrder, skipOptional: ! $proceedOptional);

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
                    app(EApprovalApprovedFileDistributionService::class)->send($submission);
                    if ($submission->isExternalSubmission() && $this->settings->notifyExternalOnApproved()) {
                        $package = $this->externalPackages->mintPackageLinks($submission);
                        $this->mail->dispatchToExternalSubmitter(
                            $submission,
                            EApprovalExternalMailEvent::APPROVED,
                            $actor->name,
                            packageLinks: $package['links'],
                            packageNote: $package['note'],
                        );
                    }
                    $this->audit->log(
                        'request_approved_final',
                        $submission->id,
                        $submission->document_no,
                        $actor,
                        WorkspaceAuditChanges::of([
                            'status' => [
                                'from' => $previousSubmissionStatus,
                                'to' => EApprovalSubmissionStatus::APPROVED,
                            ],
                            'approval_status' => [
                                'from' => $previousApprovalStatus,
                                'to' => EApprovalApprovalStatus::APPROVED,
                            ],
                        ]),
                        entityLabel: $submission->document_no,
                    );
                    $submission->loadMissing(['form', 'values.field']);
                    $this->controlledDocumentHook->afterSubmissionMutation($submission, $actor);
                }
            } else {
                $this->audit->log(
                    'request_approved_step',
                    $submission->id,
                    "Step {$stepOrder}",
                    $actor,
                    WorkspaceAuditChanges::of([
                        'approval_status' => [
                            'from' => $previousApprovalStatus,
                            'to' => EApprovalApprovalStatus::APPROVED,
                        ],
                    ]),
                    entityLabel: $submission->document_no,
                );
            }

            if ($submission->status !== EApprovalSubmissionStatus::APPROVED) {
                $bandStillPending = $submission->approvals()
                    ->where('status', EApprovalApprovalStatus::PENDING)
                    ->whereHas('step', static fn ($query) => $query->where('step_order', $stepOrder))
                    ->exists();
                if (! $bandStillPending) {
                    $this->mail->dispatchStepApproved($submission, $stepOrder, $actor->name);
                }
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
            $previousApprovalStatus = (string) $approval->status;
            $previousSubmissionStatus = (string) $submission->status;

            $approval->status = EApprovalApprovalStatus::REJECTED;
            $approval->remarks = $remarks;
            $approval->acted_at = now();
            $approval->save();

            EApprovalRequestApproval::query()
                ->where('submission_id', $submission->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->where('id', '!=', $approval->id)
                ->update([
                    'status' => EApprovalApprovalStatus::INVALIDATED,
                    'remarks' => __('Invalidated because the request was rejected.'),
                    'acted_at' => now(),
                ]);

            $submission->status = EApprovalSubmissionStatus::REJECTED;
            $submission->save();
            $this->controlledDocumentHook->afterSubmissionClosed($submission);

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
            if ($submission->isExternalSubmission()) {
                $this->mail->dispatchToExternalSubmitter(
                    $submission,
                    EApprovalExternalMailEvent::REJECTED,
                    $actor->name,
                    $remarks,
                );
            }
            $this->audit->log(
                'request_rejected',
                $submission->id,
                $remarks,
                $actor,
                WorkspaceAuditChanges::of([
                    'status' => [
                        'from' => $previousSubmissionStatus,
                        'to' => EApprovalSubmissionStatus::REJECTED,
                    ],
                    'approval_status' => [
                        'from' => $previousApprovalStatus,
                        'to' => EApprovalApprovalStatus::REJECTED,
                    ],
                ]),
                entityLabel: $submission->document_no,
            );
            $this->comments->add(
                $submission,
                __('Rejected: :remarks', ['remarks' => $remarks]),
                $actor,
                notifyStakeholders: false,
            );
            $submission->loadMissing(['form', 'values.field']);

            return $approval->fresh(['submission.form', 'approver', 'step']);
        });
    }
}
