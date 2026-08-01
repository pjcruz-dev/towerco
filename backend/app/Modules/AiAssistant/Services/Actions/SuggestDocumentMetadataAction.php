<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use RuntimeException;

/**
 * Proposes document metadata updates (title / status / expires_at). Executes only after confirm.
 */
final class SuggestDocumentMetadataAction implements AssistantActionInterface
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    public function name(): string
    {
        return 'suggest_document_metadata';
    }

    public function description(): string
    {
        return 'Propose document metadata changes. Applies only after user confirmation.';
    }

    public function requiredModule(): ?string
    {
        return 'documents';
    }

    public function requiredDomainPermissions(): array
    {
        return ['documents:upload'];
    }

    public function argumentRules(): array
    {
        return [
            'document_id' => ['required', 'uuid'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'in:draft,final,superseded'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $documentId = isset($args['document_id']) && is_string($args['document_id']) ? $args['document_id'] : null;
        $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : null;
        $expiresAt = isset($args['expires_at']) && is_string($args['expires_at']) ? trim($args['expires_at']) : null;

        if ($title === null && preg_match('/\btitle\s*[:=]\s*[\'"]?([^\'"\n]+)[\'"]?/i', $question, $m) === 1) {
            $title = trim($m[1]);
        }
        if ($expiresAt === null && preg_match('/\bexpir(?:es|y)?\s*[:=]?\s*(\d{4}-\d{2}-\d{2})\b/i', $question, $m) === 1) {
            $expiresAt = $m[1];
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Update document metadata',
            summary: 'I can update document metadata after you confirm. Provide the document ID and the fields to change.',
            payload: [
                'document_id' => $documentId,
                'title' => $title,
                'status' => $args['status'] ?? null,
                'expires_at' => $expiresAt,
            ],
            preview: [
                'document_id' => $documentId,
                'title' => $title,
                'status' => $args['status'] ?? null,
                'expires_at' => $expiresAt,
            ],
            editableFields: [
                ['key' => 'document_id', 'label' => 'Document ID', 'type' => 'text', 'required' => true],
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => false],
                ['key' => 'status', 'label' => 'Status (draft|final|superseded)', 'type' => 'text', 'required' => false],
                ['key' => 'expires_at', 'label' => 'Expires at (YYYY-MM-DD)', 'type' => 'text', 'required' => false],
            ],
            moduleKey: 'documents',
            confirmLabel: 'Apply metadata',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $documentId = isset($payload['document_id']) ? (string) $payload['document_id'] : '';
        if ($documentId === '') {
            throw new RuntimeException('document_id is required.');
        }

        $document = Document::query()->find($documentId);
        if ($document === null) {
            throw new RuntimeException('Document not found.');
        }

        $update = [];
        if (isset($payload['title']) && is_string($payload['title']) && trim($payload['title']) !== '') {
            $update['title'] = trim($payload['title']);
        }
        if (isset($payload['status']) && is_string($payload['status']) && trim($payload['status']) !== '') {
            $status = trim($payload['status']);
            if (! in_array($status, [DocumentStatus::DRAFT, DocumentStatus::FINAL, DocumentStatus::SUPERSEDED], true)) {
                throw new RuntimeException('Invalid document status.');
            }
            $update['status'] = $status;
        }
        if (array_key_exists('expires_at', $payload)) {
            $update['expires_at'] = $payload['expires_at'];
        }

        if ($update === []) {
            throw new RuntimeException('No metadata fields provided to update.');
        }

        $updated = $this->documents->updateMetadata($document, $update, $viewer);
        $fresh = $document->fresh() ?? $document;

        return new ActionExecutionResult(
            ok: true,
            entityType: 'document',
            entityId: (string) $fresh->id,
            entityLabel: $fresh->title,
            meta: [
                'title' => $fresh->title,
                'status' => $fresh->status,
                'expires_at' => $fresh->expires_at?->toDateString(),
                'service_result' => $updated,
            ],
            href: '/documents',
        );
    }
}
