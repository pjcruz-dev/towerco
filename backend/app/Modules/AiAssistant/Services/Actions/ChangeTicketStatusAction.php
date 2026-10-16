<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Models\TicketingTicket;
use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Validation\ValidationException;

/**
 * Change one ticket's status through the ticket service.
 */
final class ChangeTicketStatusAction implements AssistantActionInterface
{
    public function __construct(
        private readonly TicketingTicketService $tickets,
    ) {}

    public function name(): string
    {
        return 'change_ticket_status';
    }

    public function description(): string
    {
        return 'Change one ticket status: open, in_progress, resolved, or closed. Resolving needs a comment.';
    }

    public function requiredModule(): ?string
    {
        return 'ticketing';
    }

    public function requiredDomainPermissions(): array
    {
        return ['ticketing:tickets:manage'];
    }

    public function plannerArgumentRules(): array
    {
        return [
            'ticket_number' => ['required', 'string', 'max:20'],
            'status' => ['required', 'string', 'max:40'],
            'resolution_comment' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    public function argumentRules(): array
    {
        return $this->plannerArgumentRules();
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $number = $this->stringFrom($args, 'ticket_number') ?? $this->numberFrom($question);
        $status = $this->normalizeStatus($this->stringFrom($args, 'status') ?? $this->statusFrom($question));
        if ($number === null || $status === null) {
            throw ValidationException::withMessages([
                'ticket' => ['Include the ticket number and the status, such as in progress or closed.'],
            ]);
        }

        $comment = $this->stringFrom($args, 'resolution_comment') ?? $this->commentFrom($question);
        $payload = [
            'ticket_number' => $number,
            'status' => $status,
        ];
        if ($comment !== null) {
            $payload['resolution_comment'] = $comment;
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Change ticket status',
            summary: sprintf('%s will be %s.', $number, str_replace('_', ' ', $status)),
            payload: $payload,
            preview: $payload,
            moduleKey: 'ticketing',
            confirmLabel: 'Change status',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $number = $this->integerNumber((string) ($payload['ticket_number'] ?? ''));
        $ticket = TicketingTicket::query()->where('ticket_number', $number)->first();
        if (! $ticket instanceof TicketingTicket) {
            throw ValidationException::withMessages([
                'ticket_number' => [sprintf('No ticket matches %s.', (string) ($payload['ticket_number'] ?? ''))],
            ]);
        }

        $data = ['status' => (string) $payload['status']];
        if (isset($payload['resolution_comment']) && is_string($payload['resolution_comment'])) {
            $data['resolution_comment'] = $payload['resolution_comment'];
        }
        $result = $this->tickets->update($ticket, $viewer, $data);
        $fresh = $result['ticket'];
        $label = $fresh->displayNumber().' is now '.str_replace('_', ' ', (string) $fresh->status);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'ticket',
            entityId: (string) $fresh->id,
            entityLabel: $label,
            meta: [
                'ticket_number' => $fresh->displayNumber(),
                'status' => (string) $fresh->status,
            ],
            href: '/tickets/'.$fresh->displayNumber(),
        );
    }

    private function integerNumber(string $raw): int
    {
        if (preg_match('/(\d+)/', $raw, $match) !== 1) {
            return 0;
        }

        return (int) $match[1];
    }

    private function numberFrom(string $question): ?string
    {
        if (preg_match('/\bTKT-(\d+)\b/i', $question, $match) !== 1) {
            return null;
        }

        return 'TKT-'.str_pad($match[1], 5, '0', STR_PAD_LEFT);
    }

    private function statusFrom(string $question): ?string
    {
        $q = mb_strtolower($question);
        if (preg_match('/\bin[\s_-]*progress\b/u', $q) === 1) {
            return TicketingTicket::STATUS_IN_PROGRESS;
        }
        if (preg_match('/\b(resolved|resolve)\b/u', $q) === 1) {
            return TicketingTicket::STATUS_RESOLVED;
        }
        if (preg_match('/\b(closed|close)\b/u', $q) === 1) {
            return TicketingTicket::STATUS_CLOSED;
        }
        if (preg_match('/\b(reopen|open)\b/u', $q) === 1) {
            return TicketingTicket::STATUS_OPEN;
        }

        return null;
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }
        $normalized = str_replace([' ', '-'], '_', mb_strtolower(trim($status)));

        return in_array($normalized, [
            TicketingTicket::STATUS_OPEN,
            TicketingTicket::STATUS_IN_PROGRESS,
            TicketingTicket::STATUS_RESOLVED,
            TicketingTicket::STATUS_CLOSED,
        ], true) ? $normalized : $this->statusFrom($status);
    }

    private function commentFrom(string $question): ?string
    {
        if (preg_match('/\b(?:because|comment)\s+(.+)$/iu', $question, $match) !== 1) {
            return null;
        }

        $comment = trim($match[1]);

        return $comment === '' ? null : $comment;
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
