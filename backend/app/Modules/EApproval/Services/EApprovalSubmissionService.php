<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalSubmissionService
{
    public function __construct(
        private readonly EApprovalDocumentSequenceService $documentNumbers,
        private readonly EApprovalSubmissionSnapshotService $snapshots,
        private readonly SubmissionWorkflowService $workflow,
        private readonly EApprovalSubmissionLifecycleService $lifecycle,
        private readonly EApprovalFormValueDisplayService $valueDisplay,
        private readonly EApprovalSubmissionValuesValidator $valuesValidator,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalSubmissionParentLinkService $parentLinks,
        private readonly EApprovalSubmissionRelatedService $relatedSubmissions,
        private readonly EApprovalDocumentLinkService $documentLinks,
        private readonly EApprovalSubmissionFinanceAuditService $financeAudit,
    ) {}

    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        string $search,
        ?string $status,
        bool $canViewAll,
    ): LengthAwarePaginator {
        $query = EApprovalSubmission::query()
            ->with(['form:id,name', 'requestor:id,name,email'])
            ->orderByDesc('created_at');

        if (! $canViewAll) {
            $query->where(static function ($q) use ($viewer): void {
                $q->where('requestor_id', $viewer->id)
                    ->orWhereIn('id', EApprovalRequestApproval::query()
                        ->where('approver_id', $viewer->id)
                        ->select('submission_id'));
            });
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn ($q) => $q->where('document_no', 'like', $like)
                ->orWhereHas('form', static fn ($f) => $f->where('name', 'like', $like)));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function assertCanView(EApprovalSubmission $submission, TenantUser $viewer, bool $canViewAll): void
    {
        if ($canViewAll) {
            return;
        }

        if ((string) $submission->requestor_id === (string) $viewer->id) {
            return;
        }

        $isApprover = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('approver_id', $viewer->id)
            ->exists();

        if (! $isApprover) {
            throw ValidationException::withMessages([
                'submission' => [__('You cannot access this submission.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function create(string $formId, array $values, TenantUser $requestor, ?string $parentSubmissionId = null): EApprovalSubmission
    {
        $form = EApprovalForm::query()->with(['fields', 'workflowTemplate.steps'])->find($formId);
        if ($form === null) {
            throw ValidationException::withMessages(['form_id' => [__('Form not found.')]]);
        }

        if ($form->status === 'draft') {
            throw ValidationException::withMessages(['form_id' => [__('This form is not published yet.')]]);
        }

        if ($form->accepts_new_submissions === false) {
            throw ValidationException::withMessages(['form_id' => [__('This form is retired and no longer accepts new submissions.')]]);
        }

        $parentId = $this->resolveParentSubmissionId($parentSubmissionId, $requestor, $form, true);
        $values = $this->enrichValuesForParent($parentId, $form, $values);
        $overspendWarning = $this->assertParentChildAmounts($form, $parentId, $values);

        $this->valuesValidator->validate($form, $values);

        return DB::connection('tenant')->transaction(function () use ($form, $values, $requestor, $parentId, $overspendWarning) {
            $snapshot = $this->snapshots->capture($form);
            $documentNo = $this->documentNumbers->nextDocumentNumber($form, $values);

            $submission = EApprovalSubmission::query()->create([
                'id' => (string) Str::uuid(),
                'document_no' => $documentNo,
                'form_id' => $form->id,
                'requestor_id' => $requestor->id,
                'parent_submission_id' => $parentId,
                'status' => EApprovalSubmissionStatus::PENDING,
                'current_step' => 1,
                'schema_snapshot_json' => $snapshot['schema_snapshot_json'],
                'workflow_snapshot_json' => $snapshot['workflow_snapshot_json'],
                'workflow_version_id' => $snapshot['workflow_version_id'],
            ]);

            $this->persistValues($submission, $form, $values);
            $this->workflow->initiateWorkflow($submission, $form, $values);
            $this->audit->log('submission_created', $submission->id, $documentNo, $requestor);
            $this->financeAudit->logParentLinkChange((string) $submission->id, null, $parentId, $requestor);
            $this->logOverspendPolicyAllowedIfNeeded($overspendWarning, (string) $submission->id, $requestor);

            return $submission->fresh(['form', 'requestor', 'values.field', 'approvals.step', 'approvals.approver']);
        });
    }

    public function findRequestorDraft(TenantUser $requestor, string $formId): ?EApprovalSubmission
    {
        return EApprovalSubmission::query()
            ->where('form_id', $formId)
            ->where('requestor_id', $requestor->id)
            ->where('status', EApprovalSubmissionStatus::DRAFT)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function createDraft(
        string $formId,
        array $values,
        TenantUser $requestor,
        ?string $parentSubmissionId = null,
        bool $updateParentLink = true,
    ): EApprovalSubmission {
        $existing = $this->findRequestorDraft($requestor, $formId);
        if ($existing !== null) {
            return $this->updateDraft($existing, $values, $requestor, $parentSubmissionId, $updateParentLink);
        }

        $form = EApprovalForm::query()->with(['fields', 'workflowTemplate.steps'])->find($formId);
        if ($form === null) {
            throw ValidationException::withMessages(['form_id' => [__('Form not found.')]]);
        }

        if ($form->status === 'draft') {
            throw ValidationException::withMessages(['form_id' => [__('This form is not published yet.')]]);
        }

        if ($form->accepts_new_submissions === false) {
            throw ValidationException::withMessages(['form_id' => [__('This form is retired and no longer accepts new submissions.')]]);
        }

        $parentId = $this->resolveParentSubmissionId($parentSubmissionId, $requestor, $form, $updateParentLink);
        $values = $this->enrichValuesForParent($parentId, $form, $values);
        $overspendWarning = $this->assertParentChildAmounts($form, $parentId, $values);

        $this->valuesValidator->validate($form, $values, false);

        return DB::connection('tenant')->transaction(function () use ($form, $values, $requestor, $parentId, $overspendWarning) {
            $snapshot = $this->snapshots->capture($form);
            $draftNo = 'DRAFT-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

            $submission = EApprovalSubmission::query()->create([
                'id' => (string) Str::uuid(),
                'document_no' => $draftNo,
                'form_id' => $form->id,
                'requestor_id' => $requestor->id,
                'parent_submission_id' => $parentId,
                'status' => EApprovalSubmissionStatus::DRAFT,
                'current_step' => 0,
                'schema_snapshot_json' => $snapshot['schema_snapshot_json'],
                'workflow_snapshot_json' => $snapshot['workflow_snapshot_json'],
                'workflow_version_id' => $snapshot['workflow_version_id'],
            ]);

            $this->persistValues($submission, $form, $values);
            $this->audit->log('submission_draft_saved', $submission->id, $draftNo, $requestor);
            $this->financeAudit->logParentLinkChange((string) $submission->id, null, $parentId, $requestor);
            $this->logOverspendPolicyAllowedIfNeeded($overspendWarning, (string) $submission->id, $requestor);

            return $submission->fresh(['form', 'requestor', 'values.field']);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateDraft(
        EApprovalSubmission $submission,
        array $values,
        TenantUser $requestor,
        ?string $parentSubmissionId = null,
        bool $updateParentLink = false,
    ): EApprovalSubmission {
        $this->assertDraftOwner($submission, $requestor);

        $previousParentId = $submission->parent_submission_id;
        $form = EApprovalForm::query()->with('fields')->findOrFail($submission->form_id);
        $parentId = $this->resolveParentSubmissionId(
            $parentSubmissionId,
            $requestor,
            $form,
            $updateParentLink,
            $submission->parent_submission_id,
            (string) $submission->id,
        );
        $values = $this->enrichValuesForParent($parentId, $form, $values);
        $overspendWarning = $this->assertParentChildAmounts($form, $parentId, $values, (string) $submission->id);
        $this->valuesValidator->validate($form, $values, false);

        return DB::connection('tenant')->transaction(function () use ($submission, $form, $values, $requestor, $parentId, $updateParentLink, $overspendWarning, $previousParentId) {
            EApprovalFormValue::query()->where('submission_id', $submission->id)->delete();
            $this->persistValues($submission, $form, $values);
            if ($updateParentLink) {
                $submission->parent_submission_id = $parentId;
            }
            $submission->touch();
            $submission->save();
            $this->audit->log('submission_draft_saved', $submission->id, $submission->document_no, $requestor);
            $this->financeAudit->logParentLinkChange(
                (string) $submission->id,
                $previousParentId,
                $submission->parent_submission_id,
                $requestor,
            );
            $this->logOverspendPolicyAllowedIfNeeded($overspendWarning, (string) $submission->id, $requestor);

            return $submission->fresh(['form', 'requestor', 'values.field']);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function submitDraft(
        EApprovalSubmission $submission,
        array $values,
        TenantUser $requestor,
        ?string $parentSubmissionId = null,
        bool $updateParentLink = false,
    ): EApprovalSubmission {
        $this->assertDraftOwner($submission, $requestor);

        $previousParentId = $submission->parent_submission_id;
        $form = EApprovalForm::query()->with(['fields', 'workflowTemplate.steps'])->findOrFail($submission->form_id);
        $parentId = $this->resolveParentSubmissionId(
            $parentSubmissionId,
            $requestor,
            $form,
            $updateParentLink,
            $submission->parent_submission_id,
            (string) $submission->id,
        );
        $values = $this->enrichValuesForParent($parentId, $form, $values);
        $overspendWarning = $this->assertParentChildAmounts($form, $parentId, $values, (string) $submission->id);
        $this->valuesValidator->validate($form, $values, true);

        return DB::connection('tenant')->transaction(function () use ($submission, $form, $values, $requestor, $parentId, $updateParentLink, $overspendWarning, $previousParentId) {
            $documentNo = $this->documentNumbers->nextDocumentNumber($form, $values);
            $snapshot = $this->snapshots->capture($form);

            EApprovalFormValue::query()->where('submission_id', $submission->id)->delete();

            $submission->fill([
                'document_no' => $documentNo,
                'status' => EApprovalSubmissionStatus::PENDING,
                'current_step' => 1,
                'schema_snapshot_json' => $snapshot['schema_snapshot_json'],
                'workflow_snapshot_json' => $snapshot['workflow_snapshot_json'],
                'workflow_version_id' => $snapshot['workflow_version_id'],
            ]);
            if ($updateParentLink || $submission->parent_submission_id === null) {
                $submission->parent_submission_id = $parentId;
            }
            $submission->save();

            $this->persistValues($submission, $form, $values);
            $this->workflow->initiateWorkflow($submission, $form, $values);
            $this->audit->log('submission_created', $submission->id, $documentNo, $requestor);
            $this->financeAudit->logParentLinkChange(
                (string) $submission->id,
                $previousParentId,
                $submission->parent_submission_id,
                $requestor,
            );
            $this->logOverspendPolicyAllowedIfNeeded($overspendWarning, (string) $submission->id, $requestor);

            return $submission->fresh(['form', 'requestor', 'values.field', 'approvals.step', 'approvals.approver']);
        });
    }

    public function cancel(EApprovalSubmission $submission, TenantUser $actor): EApprovalSubmission
    {
        if ((string) $submission->requestor_id !== (string) $actor->id) {
            throw ValidationException::withMessages([
                'submission' => [__('Only the requestor can cancel this submission.')],
            ]);
        }

        if (! in_array($submission->status, [EApprovalSubmissionStatus::PENDING, EApprovalSubmissionStatus::DRAFT], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Only pending or draft submissions can be cancelled.')],
            ]);
        }

        $submission->status = EApprovalSubmissionStatus::CANCELLED;
        $submission->save();

        if ($submission->status === EApprovalSubmissionStatus::PENDING) {
            EApprovalRequestApproval::query()
                ->where('submission_id', $submission->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->update(['status' => EApprovalApprovalStatus::CANCELLED]);
        }

        $this->audit->log('submission_cancelled', $submission->id, null, $actor);

        return $submission->fresh(['form', 'requestor']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function resubmit(EApprovalSubmission $submission, array $values, TenantUser $actor): EApprovalSubmission
    {
        if (! in_array($submission->status, [EApprovalSubmissionStatus::RETURNED, EApprovalSubmissionStatus::REJECTED], true)
            && ! $actor->can('e_approval:forms:manage')) {
            throw ValidationException::withMessages([
                'submission' => [__('This submission cannot be resubmitted.')],
            ]);
        }

        if ((string) $submission->requestor_id !== (string) $actor->id && ! $actor->can('e_approval:forms:manage')) {
            throw ValidationException::withMessages([
                'submission' => [__('Only the requestor can resubmit.')],
            ]);
        }

        $form = EApprovalForm::query()->with(['fields', 'workflowTemplate.steps'])->findOrFail($submission->form_id);

        $overspendWarning = $this->assertLinkedParentAmounts($submission, $form, $values);
        $this->valuesValidator->validate($form, $values);

        return DB::connection('tenant')->transaction(function () use ($submission, $form, $values, $actor, $overspendWarning) {
            EApprovalRequestApproval::query()->where('submission_id', $submission->id)->delete();
            EApprovalFormValue::query()->where('submission_id', $submission->id)->delete();

            $snapshot = $this->snapshots->capture($form);
            $submission->fill([
                'status' => EApprovalSubmissionStatus::PENDING,
                'current_step' => 1,
                'schema_snapshot_json' => $snapshot['schema_snapshot_json'],
                'workflow_snapshot_json' => $snapshot['workflow_snapshot_json'],
                'workflow_version_id' => $snapshot['workflow_version_id'],
            ]);
            $submission->save();

            $this->persistValues($submission, $form, $values);
            $this->workflow->initiateWorkflow($submission, $form, $values);
            $this->audit->log('submission_resubmitted', $submission->id, null, $actor);
            $this->logOverspendPolicyAllowedIfNeeded($overspendWarning, (string) $submission->id, $actor);

            return $submission->fresh(['form', 'requestor', 'values.field', 'approvals.step', 'approvals.approver']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form', 'requestor', 'values.field', 'approvals.step', 'approvals.approver', 'attachments']);
        $snapshotFields = $this->snapshotFieldsFromSubmission($submission);

        return array_merge($submission->toListRow(), $this->lifecycle->manualFollowUpMeta($submission), [
            'form_schema_version_at_submit' => $this->schemaVersionFromSnapshot($submission),
            'workflow_version_id' => $submission->workflow_version_id,
            'parent_submission_id' => $submission->parent_submission_id,
            'related_submissions' => $this->relatedSubmissions->listForSubmission($submission),
            'related_form_navigation' => $this->relatedSubmissions->relatedFormNavigation($submission->form),
            'form_fields' => $snapshotFields,
            'document_links' => $this->documentLinks->listOutgoing($submission),
            'incoming_document_links' => $this->documentLinks->listIncoming($submission),
            'values' => $this->valueDisplay->mapForApi($submission->values),
            'approvals' => $submission->approvals->map(static fn ($a) => $a->toListRow())->values()->all(),
            'attachments' => $submission->attachments->map(static fn ($a) => [
                'id' => (string) $a->id,
                'field_name' => $a->field_name,
                'file_name' => $a->file_name,
                'file_path' => $a->file_path,
            ])->values()->all(),
        ]);
    }

    /**
     * @return list<array{
     *     id: string,
     *     type: string|null,
     *     name: string|null,
     *     label: string|null,
     *     semantic_type: string|null,
     *     validation: mixed,
     *     options: mixed
     * }>
     */
    private function snapshotFieldsFromSubmission(EApprovalSubmission $submission): array
    {
        $raw = $submission->schema_snapshot_json;
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        $fields = is_array($decoded) ? ($decoded['fields'] ?? null) : null;
        if (! is_array($fields)) {
            return [];
        }

        return collect($fields)
            ->filter(static fn ($f): bool => is_array($f))
            ->map(static function (array $field): array {
                return [
                    'id' => (string) ($field['id'] ?? ''),
                    'type' => isset($field['type']) ? (string) $field['type'] : null,
                    'name' => isset($field['name']) ? (string) $field['name'] : null,
                    'label' => isset($field['label']) ? (string) $field['label'] : null,
                    'semantic_type' => isset($field['semantic_type']) ? (string) $field['semantic_type'] : null,
                    'validation' => $field['validation'] ?? null,
                    'options' => $field['options'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function schemaVersionFromSnapshot(EApprovalSubmission $submission): ?int
    {
        $raw = $submission->schema_snapshot_json;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $version = is_array($decoded) ? ($decoded['form']['schema_version'] ?? null) : null;

        return is_numeric($version) ? (int) $version : null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assertDraftOwner(EApprovalSubmission $submission, TenantUser $requestor): void
    {
        if ($submission->status !== EApprovalSubmissionStatus::DRAFT) {
            throw ValidationException::withMessages([
                'submission' => [__('This submission is not a draft.')],
            ]);
        }

        if ((string) $submission->requestor_id !== (string) $requestor->id) {
            throw ValidationException::withMessages([
                'submission' => [__('You cannot edit this draft.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveParentSubmissionId(
        ?string $parentSubmissionId,
        TenantUser $requestor,
        EApprovalForm $form,
        bool $updateParentLink,
        ?string $existingParentId = null,
        ?string $currentSubmissionId = null,
    ): ?string {
        if ($updateParentLink) {
            return $this->parentLinks->resolve($parentSubmissionId, $requestor, $form, $currentSubmissionId);
        }

        if ($existingParentId !== null && $existingParentId !== '') {
            return $this->parentLinks->resolve($existingParentId, $requestor, $form, $currentSubmissionId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function enrichValuesForParent(?string $parentId, EApprovalForm $form, array $values): array
    {
        if ($parentId === null) {
            return $values;
        }

        /** @var EApprovalSubmission $parent */
        $parent = EApprovalSubmission::query()->with('form')->findOrFail($parentId);

        return $this->parentLinks->enrichValues($parent, $form, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null
     */
    public function assertLinkedParentAmounts(EApprovalSubmission $submission, EApprovalForm $form, array $values): ?array
    {
        $parentId = $submission->parent_submission_id;
        if ($parentId === null || $parentId === '') {
            return null;
        }

        return $this->assertParentChildAmounts($form, $parentId, $values, (string) $submission->id);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null
     */
    private function assertParentChildAmounts(
        EApprovalForm $form,
        ?string $parentId,
        array $values,
        ?string $excludeSubmissionId = null,
    ): ?array {
        if ($parentId === null || trim($parentId) === '') {
            return null;
        }

        return $this->parentLinks->assertChildAmounts($form, $parentId, $values, $excludeSubmissionId);
    }

    /**
     * @param  array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null  $context
     */
    private function logOverspendPolicyAllowedIfNeeded(
        ?array $context,
        string $submissionId,
        ?TenantUser $actor,
    ): void {
        if ($context === null) {
            return;
        }

        $this->financeAudit->logOverspendPolicyAllowed($context, $submissionId, $actor);
    }

    private function persistValues(EApprovalSubmission $submission, EApprovalForm $form, array $values): void
    {
        $fieldsByName = $form->fields->keyBy('name');

        foreach ($values as $key => $raw) {
            $field = $fieldsByName->get($key) ?? $form->fields->firstWhere('id', $key);
            if ($field === null) {
                continue;
            }

            $stored = is_scalar($raw) ? (string) $raw : json_encode($raw, JSON_THROW_ON_ERROR);

            EApprovalFormValue::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'field_id' => $field->id,
                'value' => $stored,
            ]);
        }
    }
}
