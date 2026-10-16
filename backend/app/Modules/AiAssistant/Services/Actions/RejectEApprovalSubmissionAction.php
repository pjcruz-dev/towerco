<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\ApprovalDecisionService;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Propose rejecting a pending E-Forms submission. The decision is saved only after confirm.
 */
final class RejectEApprovalSubmissionAction implements AssistantActionInterface
{
    public function __construct(
        private readonly ApprovalDecisionService $decisions,
    ) {}

    public function name(): string
    {
        return 'reject_eapproval_submission';
    }

    public function description(): string
    {
        return 'Reject a pending E-Forms approval. Use only when the user says reject. Cancel is a different action. Saves only after confirmation.';
    }

    public function requiredModule(): ?string
    {
        return 'e_approval';
    }

    public function requiredDomainPermissions(): array
    {
        return ['e_approval:approve'];
    }

    public function argumentRules(): array
    {
        return [
            'approval_id' => ['required', 'uuid'],
            'remarks' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }

    /**
     * Fields the catalog planner may fill. propose() resolves these to the execute payload.
     *
     * @return array<string, mixed>
     */
    public function plannerArgumentRules(): array
    {
        return [
            'document_no' => ['required', 'string', 'max:80'],
            'approval_id' => ['sometimes', 'nullable', 'uuid'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $documentNo = $this->argumentString($args, 'document_no');
        if ($documentNo !== null) {
            $documentNo = strtoupper($documentNo);
        }
        if ($documentNo === null) {
            $documentNo = $this->documentNo($question);
        }
        if ($documentNo === null) {
            $conversationId = isset($args['conversation_id']) && is_string($args['conversation_id'])
                ? $args['conversation_id']
                : null;
            $documentNo = $this->documentNoFromConversation($conversationId);
        }

        if ($documentNo === null) {
            throw ValidationException::withMessages([
                'document_no' => ['Include the E-Forms document number you want to reject.'],
            ]);
        }

        $submission = EApprovalSubmission::query()->where('document_no', $documentNo)->first();
        if (! $submission instanceof EApprovalSubmission) {
            throw ValidationException::withMessages([
                'document_no' => [sprintf('No E-Forms submission matches %s.', $documentNo)],
            ]);
        }

        if (in_array((string) $submission->status, [
            EApprovalSubmissionStatus::CANCELLED,
            EApprovalSubmissionStatus::APPROVED,
            EApprovalSubmissionStatus::REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'document_no' => [sprintf('%s is already %s.', $documentNo, (string) $submission->status)],
            ]);
        }

        $approval = $this->approvalFromArgs($submission, $args) ?? $this->pendingApproval($submission, $viewer);
        if (! $approval instanceof EApprovalRequestApproval) {
            throw ValidationException::withMessages([
                'approval' => [sprintf('%s has no pending approval step to reject. Current status: %s.', $documentNo, (string) $submission->status)],
            ]);
        }

        $remarks = $this->argumentString($args, 'remarks');
        if ($remarks === null || strlen($remarks) < 5) {
            $remarks = $this->remarks($question);
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Reject request',
            summary: sprintf(
                'I can reject %s. It is %s. Nothing is saved until you confirm.',
                $documentNo,
                (string) $submission->status,
            ),
            payload: [
                'approval_id' => (string) $approval->id,
                'remarks' => $remarks,
            ],
            preview: [
                'document_no' => $documentNo,
                'status' => (string) $submission->status,
                'decision' => 'rejected',
                'remarks' => $remarks,
            ],
            editableFields: [
                ['key' => 'remarks', 'label' => 'Rejection remarks', 'type' => 'textarea', 'required' => true],
            ],
            moduleKey: 'e_approval',
            confirmLabel: 'Reject request',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $approval = EApprovalRequestApproval::query()->find((string) $payload['approval_id']);
        if (! $approval instanceof EApprovalRequestApproval) {
            throw ValidationException::withMessages([
                'approval_id' => ['That approval step no longer exists.'],
            ]);
        }

        $updated = $this->decisions->decide(
            $approval,
            'rejected',
            (string) $payload['remarks'],
            null,
            $viewer,
        );
        $updated->loadMissing('submission');
        $submission = $updated->submission;
        $documentNo = (string) ($submission?->document_no ?? $updated->submission_id);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'e_approval_submission',
            entityId: (string) ($submission?->id ?? $updated->submission_id),
            entityLabel: sprintf('%s is now rejected', $documentNo),
            meta: [
                'document_no' => $documentNo,
                'status' => (string) ($submission?->status ?? EApprovalSubmissionStatus::REJECTED),
            ],
            href: '/e-approval/submissions/'.rawurlencode((string) ($submission?->id ?? '')),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function argumentString(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function approvalFromArgs(EApprovalSubmission $submission, array $args): ?EApprovalRequestApproval
    {
        $approvalId = $this->argumentString($args, 'approval_id');
        if ($approvalId === null) {
            return null;
        }

        $approval = EApprovalRequestApproval::query()
            ->where('id', $approvalId)
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->first();

        return $approval instanceof EApprovalRequestApproval ? $approval : null;
    }

    private function pendingApproval(EApprovalSubmission $submission, TenantUser $viewer): ?EApprovalRequestApproval
    {
        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->orderBy('created_at')
            ->get();

        $mine = $pending->first(
            static fn (EApprovalRequestApproval $approval): bool => (string) $approval->approver_id === (string) $viewer->id,
        );

        return $mine instanceof EApprovalRequestApproval ? $mine : $pending->first();
    }

    private function remarks(string $question): string
    {
        if (preg_match('/\b(?:because|reason|remarks?)\b[:\s]+(.{5,500})/iu', $question, $match) === 1) {
            return trim($match[1]);
        }

        return 'Rejected from the assistant.';
    }

    private function documentNo(string $text): ?string
    {
        if (preg_match('/\b([A-Z][A-Z0-9]*(?:-+[A-Z0-9]+)+)\b/i', $text, $match) !== 1) {
            return null;
        }

        $code = strtoupper($match[1]);
        if (str_contains($code, '--') || str_contains($code, 'EAPPROVAL') || preg_match('/^[A-Z]{2,}-[A-Z0-9]{1,3}-\d{3,}$/', $code) === 1) {
            return $code;
        }

        return null;
    }

    private function documentNoFromConversation(?string $conversationId): ?string
    {
        if ($conversationId === null || $conversationId === '') {
            return null;
        }

        $messages = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['content']);

        foreach ($messages as $message) {
            $found = $this->documentNo((string) $message->content);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
