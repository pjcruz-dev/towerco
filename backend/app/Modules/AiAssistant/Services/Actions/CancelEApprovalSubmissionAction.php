<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Propose cancelling an E-Forms submission. Saved only after confirm.
 * Cancel is the requestor's action. It is not an approval rejection.
 */
final class CancelEApprovalSubmissionAction implements AssistantActionInterface
{
    public function __construct(
        private readonly EApprovalSubmissionService $submissions,
    ) {}

    public function name(): string
    {
        return 'cancel_eapproval_submission';
    }

    public function description(): string
    {
        return 'Cancel an E-Forms submission the user asked to cancel or withdraw. Not a rejection. Saves only after confirmation.';
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
            'submission_id' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function plannerArgumentRules(): array
    {
        return [
            'document_no' => ['required', 'string', 'max:80'],
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
                'document_no' => ['Include the E-Forms document number you want to cancel.'],
            ]);
        }

        $submission = EApprovalSubmission::query()->where('document_no', $documentNo)->first();
        if (! $submission instanceof EApprovalSubmission) {
            throw ValidationException::withMessages([
                'document_no' => [sprintf('No E-Forms submission matches %s.', $documentNo)],
            ]);
        }

        if ((string) $submission->requestor_id !== (string) $viewer->id) {
            throw ValidationException::withMessages([
                'submission' => ['Only the requestor can cancel this submission.'],
            ]);
        }

        if (! in_array((string) $submission->status, [
            EApprovalSubmissionStatus::PENDING,
            EApprovalSubmissionStatus::DRAFT,
            EApprovalSubmissionStatus::RETURNED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => [sprintf('%s is %s and cannot be cancelled.', $documentNo, (string) $submission->status)],
            ]);
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Cancel request',
            summary: sprintf(
                'I can cancel %s. It is %s. Nothing is saved until you confirm.',
                $documentNo,
                (string) $submission->status,
            ),
            payload: [
                'submission_id' => (string) $submission->id,
            ],
            preview: [
                'document_no' => $documentNo,
                'status' => (string) $submission->status,
                'decision' => 'cancelled',
            ],
            editableFields: [],
            moduleKey: 'e_approval',
            confirmLabel: 'Cancel request',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $submission = EApprovalSubmission::query()->find((string) $payload['submission_id']);
        if (! $submission instanceof EApprovalSubmission) {
            throw ValidationException::withMessages([
                'submission_id' => ['That submission no longer exists.'],
            ]);
        }

        $updated = $this->submissions->cancel($submission, $viewer);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'e_approval_submission',
            entityId: (string) $updated->id,
            entityLabel: sprintf('%s is now cancelled', (string) $updated->document_no),
            meta: [
                'document_no' => (string) $updated->document_no,
                'status' => (string) $updated->status,
            ],
            href: '/e-approval/submissions/'.rawurlencode((string) $updated->id),
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
