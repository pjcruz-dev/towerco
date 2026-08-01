<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\Identity\Models\TenantUser;
use RuntimeException;

/**
 * Proposes an E-Approval draft submission. Requires a form_id on confirm (user must supply).
 */
final class DraftEApprovalSubmissionAction implements AssistantActionInterface
{
    public function __construct(
        private readonly EApprovalSubmissionService $submissions,
    ) {}

    public function name(): string
    {
        return 'draft_e_approval_submission';
    }

    public function description(): string
    {
        return 'Propose drafting an E-Approval submission. Requires form_id before confirm.';
    }

    public function requiredModule(): ?string
    {
        return 'e_approval';
    }

    public function requiredDomainPermissions(): array
    {
        return ['e_approval:submissions:create'];
    }

    public function argumentRules(): array
    {
        return [
            'form_id' => ['required', 'uuid'],
            'values' => ['sometimes', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $notes = isset($args['notes']) && is_string($args['notes'])
            ? trim($args['notes'])
            : trim($question);

        $formId = isset($args['form_id']) && is_string($args['form_id']) ? $args['form_id'] : null;
        $values = isset($args['values']) && is_array($args['values']) ? $args['values'] : [];
        if ($notes !== '' && $values === []) {
            $values = ['_assistant_notes' => $notes];
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Draft E-Approval submission',
            summary: 'I can create an E-Approval draft after you confirm. Provide a published form ID — nothing is submitted until you confirm.',
            payload: [
                'form_id' => $formId,
                'values' => $values,
                'notes' => $notes !== '' ? $notes : null,
            ],
            preview: [
                'form_id' => $formId,
                'notes' => $notes,
                'as_draft' => true,
            ],
            editableFields: [
                ['key' => 'form_id', 'label' => 'Form ID (UUID)', 'type' => 'text', 'required' => true],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
            ],
            moduleKey: 'e_approval',
            confirmLabel: 'Create draft submission',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $formId = isset($payload['form_id']) ? (string) $payload['form_id'] : '';
        if ($formId === '') {
            throw new RuntimeException('form_id is required to create an E-Approval draft.');
        }

        $values = isset($payload['values']) && is_array($payload['values']) ? $payload['values'] : [];
        if (isset($payload['notes']) && is_string($payload['notes']) && trim($payload['notes']) !== '') {
            $values['_assistant_notes'] = trim($payload['notes']);
        }

        $submission = $this->submissions->createDraft($formId, $values, $viewer);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'e_approval_submission',
            entityId: (string) $submission->id,
            entityLabel: $submission->document_no ?? (string) $submission->id,
            meta: [
                'document_no' => $submission->document_no,
                'status' => $submission->status,
                'form_id' => $formId,
            ],
            href: '/e-approval/submissions/'.$submission->id,
        );
    }
}
