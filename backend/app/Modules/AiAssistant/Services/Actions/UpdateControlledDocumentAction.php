<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Change a controlled document's title or department. Status stays on the document workflow.
 */
final class UpdateControlledDocumentAction implements AssistantActionInterface
{
    public function __construct(
        private readonly ControlledDocumentRegistryService $documents,
    ) {}

    public function name(): string
    {
        return 'update_controlled_document';
    }

    public function description(): string
    {
        return 'Update a controlled document title or department by its code. Does not publish, obsolete, or delete it.';
    }

    public function requiredModule(): ?string
    {
        return 'document_register';
    }

    public function requiredDomainPermissions(): array
    {
        return ['documents:controlled:manage'];
    }

    public function plannerArgumentRules(): array
    {
        return [
            'document_code' => ['required', 'string', 'max:80'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function argumentRules(): array
    {
        return $this->plannerArgumentRules();
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $code = $this->stringFrom($args, 'document_code') ?? $this->codeFrom($question);
        $title = $this->stringFrom($args, 'title') ?? $this->titleFrom($question);
        $department = $this->stringFrom($args, 'department') ?? $this->departmentFrom($question);
        if ($code === null || ($title === null && $department === null)) {
            throw ValidationException::withMessages([
                'document' => ['Include the document code and the title or department to set.'],
            ]);
        }

        $document = ControlledDocument::query()->where('document_code', $code)->first();
        if (! $document instanceof ControlledDocument) {
            throw ValidationException::withMessages([
                'document_code' => [sprintf('No controlled document matches %s.', $code)],
            ]);
        }

        $payload = ['document_code' => $code];
        if ($title !== null) {
            $payload['title'] = $title;
        }
        if ($department !== null) {
            $payload['department'] = $department;
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Update controlled document',
            summary: sprintf('%s will be updated.', $code),
            payload: $payload,
            preview: $payload,
            moduleKey: 'document_register',
            confirmLabel: 'Update document',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $code = trim((string) ($payload['document_code'] ?? ''));
        $document = ControlledDocument::query()->where('document_code', $code)->first();
        if (! $document instanceof ControlledDocument) {
            throw ValidationException::withMessages([
                'document_code' => [sprintf('No controlled document matches %s.', $code)],
            ]);
        }

        $data = [];
        if (isset($payload['title'])) {
            $data['title'] = (string) $payload['title'];
        }
        if (array_key_exists('department', $payload)) {
            $data['department'] = $payload['department'];
        }
        $updated = $this->documents->updateDetails($document, $viewer, $data);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'controlled_document',
            entityId: (string) $updated->id,
            entityLabel: sprintf('%s title is now %s.', (string) $updated->document_code, (string) $updated->title),
            meta: [
                'document_code' => (string) $updated->document_code,
                'title' => (string) $updated->title,
                'department' => $updated->department,
            ],
            href: '/controlled-documents/'.rawurlencode((string) $updated->document_code),
        );
    }

    private function codeFrom(string $question): ?string
    {
        if (preg_match('/\b([A-Z][A-Z0-9]*(?:-+[A-Z0-9]+)+)\b/i', $question, $match) !== 1) {
            return null;
        }

        return strtoupper($match[1]);
    }

    private function titleFrom(string $question): ?string
    {
        if (preg_match('/\btitle\b.+\bto\s+(.+)$/iu', $question, $match) !== 1) {
            return null;
        }

        $title = trim($match[1]);

        return $title === '' ? null : $title;
    }

    private function departmentFrom(string $question): ?string
    {
        if (preg_match('/\bdepartment\b.+\bto\s+(.+)$/iu', $question, $match) !== 1) {
            return null;
        }

        $department = trim($match[1]);

        return $department === '' ? null : $department;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function stringFrom(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
