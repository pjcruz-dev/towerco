<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalDocumentLink;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalDocumentLinkService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listOutgoing(EApprovalSubmission $submission): array
    {
        return EApprovalDocumentLink::query()
            ->with(['targetSubmission:id,document_no,status,form_id', 'targetSubmission.form:id,name'])
            ->where('source_submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EApprovalDocumentLink $link): array => $this->toOutgoingRow($link))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listIncoming(EApprovalSubmission $submission): array
    {
        return EApprovalDocumentLink::query()
            ->with(['sourceSubmission:id,document_no,status,form_id', 'sourceSubmission.form:id,name'])
            ->where('target_submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EApprovalDocumentLink $link): array => $this->toIncomingRow($link))
            ->values()
            ->all();
    }

    public function create(
        EApprovalSubmission $source,
        string $targetSubmissionId,
        string $linkType,
        TenantUser $actor,
    ): EApprovalDocumentLink {
        if ($targetSubmissionId === (string) $source->id) {
            throw ValidationException::withMessages([
                'target_submission_id' => [__('Cannot link a submission to itself.')],
            ]);
        }

        /** @var EApprovalSubmission|null $target */
        $target = EApprovalSubmission::query()->find($targetSubmissionId);
        if ($target === null) {
            throw ValidationException::withMessages([
                'target_submission_id' => [__('Target submission not found.')],
            ]);
        }

        $normalizedType = trim($linkType) !== '' ? trim($linkType) : 'related';

        if ($this->linkExists((string) $source->id, $targetSubmissionId, $normalizedType)) {
            throw ValidationException::withMessages([
                'target_submission_id' => [__('This document link already exists.')],
            ]);
        }

        $link = EApprovalDocumentLink::query()->create([
            'id' => (string) Str::uuid(),
            'source_submission_id' => $source->id,
            'target_submission_id' => $targetSubmissionId,
            'link_type' => $normalizedType,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            'document_link_created',
            (string) $source->id,
            "{$targetSubmissionId}:{$normalizedType}",
            $actor,
        );

        return $link->load(['targetSubmission.form', 'sourceSubmission.form']);
    }

    public function delete(EApprovalDocumentLink $link, TenantUser $actor): void
    {
        $this->audit->log(
            'document_link_deleted',
            (string) $link->source_submission_id,
            (string) $link->target_submission_id,
            $actor,
        );

        $link->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toOutgoingRow(EApprovalDocumentLink $link): array
    {
        return [
            'id' => (string) $link->id,
            'direction' => 'outgoing',
            'link_type' => $link->link_type,
            'submission_id' => (string) $link->target_submission_id,
            'document_no' => $link->targetSubmission?->document_no,
            'form_name' => $link->targetSubmission?->form?->name,
            'status' => $link->targetSubmission?->status,
            'created_at' => $link->created_at?->toIso8601String(),
            'target_submission_id' => (string) $link->target_submission_id,
            'target_document_no' => $link->targetSubmission?->document_no,
            'target_form_name' => $link->targetSubmission?->form?->name,
            'target_status' => $link->targetSubmission?->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toIncomingRow(EApprovalDocumentLink $link): array
    {
        return [
            'id' => (string) $link->id,
            'direction' => 'incoming',
            'link_type' => $link->link_type,
            'submission_id' => (string) $link->source_submission_id,
            'document_no' => $link->sourceSubmission?->document_no,
            'form_name' => $link->sourceSubmission?->form?->name,
            'status' => $link->sourceSubmission?->status,
            'created_at' => $link->created_at?->toIso8601String(),
            'source_submission_id' => (string) $link->source_submission_id,
            'source_document_no' => $link->sourceSubmission?->document_no,
            'source_form_name' => $link->sourceSubmission?->form?->name,
            'source_status' => $link->sourceSubmission?->status,
        ];
    }

    private function linkExists(string $sourceSubmissionId, string $targetSubmissionId, string $linkType): bool
    {
        return EApprovalDocumentLink::query()
            ->where('source_submission_id', $sourceSubmissionId)
            ->where('target_submission_id', $targetSubmissionId)
            ->where('link_type', $linkType)
            ->exists();
    }
}
