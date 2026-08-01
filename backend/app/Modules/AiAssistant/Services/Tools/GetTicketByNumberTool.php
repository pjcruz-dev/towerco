<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Models\TicketingTicket;
use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingTicketService;

final class GetTicketByNumberTool implements AssistantToolInterface
{
    public function __construct(
        private readonly TicketingTicketService $tickets,
    ) {}

    public function name(): string
    {
        return 'get_ticket_by_number';
    }

    public function description(): string
    {
        return 'Look up a ticketing ticket by number (e.g. TKT-00004).';
    }

    public function requiredModule(): ?string
    {
        return 'ticketing';
    }

    public function requiredPermissions(): array
    {
        return ['ticketing:view'];
    }

    public function argumentRules(): array
    {
        return [
            'ticket_number' => ['required', 'string', 'min:1', 'max:32'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $raw = trim((string) $args['ticket_number']);
        $numeric = $this->parseNumeric($raw);

        if ($numeric === null) {
            return new ToolResult(
                tool: $this->name(),
                ok: false,
                data: [],
                summary: 'Ticket number is required (example: TKT-00004).',
                moduleKey: 'ticketing',
                relatedRoutes: ['/ticketing'],
                rowCount: 0,
                error: 'invalid ticket_number',
            );
        }

        $canManage = $viewer->can('ticketing:tickets:manage');
        $ticket = TicketingTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email'])
            ->where('ticket_number', $numeric)
            ->when(
                ! $canManage,
                fn ($q) => $q->where(function ($inner) use ($viewer): void {
                    $inner->where('requester_id', $viewer->id)
                        ->orWhere('assignee_id', $viewer->id);
                }),
            )
            ->first();

        if (! $ticket instanceof TicketingTicket) {
            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['ticket' => null],
                summary: sprintf('No ticket found for %s (or you do not have access).', $this->display($numeric)),
                moduleKey: 'ticketing',
                relatedRoutes: ['/ticketing'],
                rowCount: 0,
            );
        }

        $row = $this->tickets->asListRow($ticket);
        $summary = sprintf(
            'Ticket %s — %s. Status: %s. Priority: %s%s.',
            $row['ticket_number'],
            $row['title'] ?? 'Untitled',
            $row['status'] ?? 'unknown',
            $row['priority'] ?? 'normal',
            isset($row['assignee']['name']) ? '. Assignee: '.$row['assignee']['name'] : '',
        );

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'ticket' => [
                    'id' => $row['id'],
                    'ticket_number' => $row['ticket_number'],
                    'title' => $row['title'] ?? null,
                    'status' => $row['status'] ?? null,
                    'priority' => $row['priority'] ?? null,
                    'assignee' => $row['assignee']['name'] ?? null,
                    'requester' => $row['requester']['name'] ?? null,
                    'href' => '/ticketing/tickets/'.$row['id'],
                ],
            ],
            summary: $summary,
            moduleKey: 'ticketing',
            relatedRoutes: ['/ticketing/tickets/'.$row['id']],
            rowCount: 1,
        );
    }

    private function parseNumeric(string $raw): ?int
    {
        $normalized = strtoupper(trim($raw));
        if (preg_match('/^(?:TKT-)?0*([0-9]{1,10})$/', $normalized, $m) === 1) {
            $value = (int) $m[1];

            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function display(int $numeric): string
    {
        return 'TKT-'.str_pad((string) $numeric, 5, '0', STR_PAD_LEFT);
    }
}
