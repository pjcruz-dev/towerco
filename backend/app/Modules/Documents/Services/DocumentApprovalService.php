<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentApprovalStatus;
use App\Modules\Documents\Support\DocumentApprovalValueMapper;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DocumentApprovalService
{
    public function __construct(
        private readonly EApprovalSubmissionService $submissions,
        private readonly DocumentApprovalValueMapper $valueMapper,
        private readonly DocumentActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $extraValues
     * @return array<string, mixed>
     */
    public function requestApproval(
        Document $document,
        string $formId,
        TenantUser $actor,
        array $extraValues = [],
    ): array {
        if ($document->e_approval_submission_id !== null
            && in_array($document->approval_status, [DocumentApprovalStatus::PENDING], true)) {
            throw ValidationException::withMessages([
                'document' => [__('This document already has an approval in progress.')],
            ]);
        }

        $document->loadMissing(['site', 'siteNode']);

        $form = EApprovalForm::query()
            ->with('fields')
            ->find($formId);

        if ($form === null) {
            throw ValidationException::withMessages([
                'form_id' => [__('Form not found.')],
            ]);
        }

        abort_unless($actor->can('e_approval:submissions:create'), 403);

        $values = $this->valueMapper->map($form, $document, $extraValues);

        return DB::connection('tenant')->transaction(function () use ($document, $formId, $values, $actor): array {
            $submission = $this->submissions->create($formId, $values, $actor);

            $document->e_approval_submission_id = $submission->id;
            $document->approval_status = DocumentApprovalStatus::PENDING;
            $document->last_touched_by_id = $actor->id;
            $document->last_touched_at = now();
            $document->save();

            $this->activity->log($document, 'approval_requested', $actor, [
                'submission_id' => (string) $submission->id,
                'document_no' => $submission->document_no,
            ]);

            return [
                'document' => $this->approvalPayload($document->fresh()),
                'submission' => [
                    'id' => (string) $submission->id,
                    'document_no' => $submission->document_no,
                    'status' => $submission->status,
                    'href' => '/e-approval/submissions/'.$submission->id,
                ],
            ];
        });
    }

    public function syncApprovalStatus(Document $document): Document
    {
        if ($document->e_approval_submission_id === null) {
            return $document;
        }

        /** @var EApprovalSubmission|null $submission */
        $submission = EApprovalSubmission::query()->find($document->e_approval_submission_id);
        if ($submission === null) {
            return $document;
        }

        $mapped = $this->mapSubmissionStatus($submission->status);
        if ($mapped === $document->approval_status) {
            return $document;
        }

        $document->approval_status = $mapped;
        if ($mapped === DocumentApprovalStatus::APPROVED && $document->status === DocumentStatus::DRAFT) {
            $document->status = DocumentStatus::FINAL;
        }
        $document->save();

        return $document;
    }

    /**
     * Read-only approval fields for list endpoints (no per-row writes).
     *
     * @return array<string, mixed>
     */
    public function approvalPayloadForList(Document $document, ?EApprovalSubmission $submission = null): array
    {
        $approvalStatus = $submission !== null
            ? $this->mapSubmissionStatus($submission->status)
            : (string) $document->approval_status;

        $submissionPayload = null;
        if ($submission !== null) {
            $submissionPayload = [
                'id' => (string) $submission->id,
                'document_no' => $submission->document_no,
                'status' => $submission->status,
                'form_name' => $submission->form?->name,
                'href' => '/e-approval/submissions/'.$submission->id,
            ];
        }

        return [
            'approval_status' => $approvalStatus,
            'e_approval_submission' => $submissionPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalPayload(Document $document): array
    {
        $document = $this->syncApprovalStatus($document);

        $submission = null;
        if ($document->e_approval_submission_id !== null) {
            $sub = EApprovalSubmission::query()
                ->with('form:id,name')
                ->find($document->e_approval_submission_id);

            if ($sub !== null) {
                $submission = [
                    'id' => (string) $sub->id,
                    'document_no' => $sub->document_no,
                    'status' => $sub->status,
                    'form_name' => $sub->form?->name,
                    'href' => '/e-approval/submissions/'.$sub->id,
                ];
            }
        }

        return [
            'approval_status' => $document->approval_status,
            'e_approval_submission' => $submission,
        ];
    }

    private function mapSubmissionStatus(string $submissionStatus): string
    {
        return match ($submissionStatus) {
            EApprovalSubmissionStatus::APPROVED => DocumentApprovalStatus::APPROVED,
            EApprovalSubmissionStatus::REJECTED => DocumentApprovalStatus::REJECTED,
            EApprovalSubmissionStatus::PENDING => DocumentApprovalStatus::PENDING,
            EApprovalSubmissionStatus::DRAFT => DocumentApprovalStatus::PENDING,
            default => DocumentApprovalStatus::NONE,
        };
    }
}
