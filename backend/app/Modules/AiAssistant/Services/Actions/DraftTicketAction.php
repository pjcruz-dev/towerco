<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingTicketService;
use App\Modules\Ticketing\Support\TicketingSourceCatalog;

final class DraftTicketAction implements AssistantActionInterface
{
    public function __construct(
        private readonly TicketingTicketService $tickets,
    ) {}

    public function name(): string
    {
        return 'draft_ticket';
    }

    public function description(): string
    {
        return 'Propose creating a ticketing ticket. Executes only after user confirmation.';
    }

    public function requiredModule(): ?string
    {
        return 'ticketing';
    }

    public function requiredDomainPermissions(): array
    {
        return ['ticketing:tickets:create'];
    }

    public function argumentRules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $title = isset($args['title']) && is_string($args['title']) && trim($args['title']) !== ''
            ? trim($args['title'])
            : $this->extractTitle($question);

        $description = isset($args['description']) && is_string($args['description'])
            ? trim($args['description'])
            : $this->extractDescription($question, $title);

        $category = isset($args['category']) && is_string($args['category']) && trim($args['category']) !== ''
            ? strtolower(trim($args['category']))
            : 'general';

        $payload = [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'category' => $category,
            'source_module' => TicketingSourceCatalog::MODULE_AI_ASSISTANT,
            'source_label' => 'Ask TowerOS',
        ];

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Create ticket',
            summary: 'I can create an open ticket for you with the details below. Nothing will be saved until you confirm.',
            payload: $payload,
            preview: [
                'title' => $payload['title'],
                'description' => $payload['description'],
                'category' => $payload['category'],
                'status' => 'open',
                'priority' => 'normal',
            ],
            editableFields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false],
                ['key' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => false],
            ],
            moduleKey: 'ticketing',
            confirmLabel: 'Create ticket',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $ticket = $this->tickets->create($viewer, [
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'category' => $payload['category'] ?? null,
            'source_module' => $payload['source_module'] ?? TicketingSourceCatalog::MODULE_AI_ASSISTANT,
            'source_label' => $payload['source_label'] ?? 'Ask TowerOS',
        ]);

        $detail = $this->tickets->asDetail($ticket, $viewer);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'ticketing_ticket',
            entityId: (string) $ticket->id,
            entityLabel: $detail['ticket_number'] ?? $ticket->title,
            meta: [
                'ticket_number' => $detail['ticket_number'] ?? null,
                'title' => $ticket->title,
                'status' => $ticket->status,
            ],
            href: '/ticketing/tickets/'.$ticket->id,
        );
    }

    private function extractTitle(string $question): string
    {
        $q = trim($question);

        if (preg_match('/\b(?:create|open|raise|file|draft)\s+(?:a\s+)?ticket\s+(?:for|about|regarding)?\s*[:=]?\s*(.+)$/iu', $q, $m) === 1) {
            $title = trim($m[1], " \t\n\r\0\x0B\"'");

            return mb_substr($title !== '' ? $title : 'Assistant ticket', 0, 255);
        }

        if (preg_match('/^(.+?)\s*[—\-–:]\s*(.+)$/u', $q, $m) === 1) {
            return mb_substr(trim($m[1]), 0, 255);
        }

        return mb_substr($q !== '' ? $q : 'Assistant ticket', 0, 255);
    }

    private function extractDescription(string $question, string $title): string
    {
        $q = trim($question);
        if ($q === $title) {
            return 'Created via Ask TowerOS confirmation.';
        }

        return $q;
    }
}
