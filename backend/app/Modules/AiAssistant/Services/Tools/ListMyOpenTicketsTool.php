<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Models\TicketingTicket;
use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingTicketService;

final class ListMyOpenTicketsTool implements AssistantToolInterface
{
    public function __construct(
        private readonly TicketingTicketService $tickets,
    ) {}

    public function name(): string
    {
        return 'list_my_open_tickets';
    }

    public function description(): string
    {
        return 'List the viewer\'s open / in-progress tickets (as requester).';
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
        return [];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $rows = [];
        $seen = [];

        foreach ([TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS] as $status) {
            $paginator = $this->tickets->paginate($viewer, [
                'page' => 1,
                'per_page' => $maxRows,
                'status' => $status,
                'mine' => true,
            ]);

            foreach ($paginator->items() as $ticket) {
                $id = (string) $ticket->id;
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $row = $this->tickets->asListRow($ticket);
                $rows[] = [
                    'id' => $row['id'],
                    'ticket_number' => $row['ticket_number'],
                    'title' => $row['title'],
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'assignee' => $row['assignee']['name'] ?? null,
                    'sla_status' => $row['sla_status'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null,
                ];
                if (count($rows) >= $maxRows) {
                    break 2;
                }
            }
        }

        $count = count($rows);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'tickets' => $rows,
                'returned' => $count,
            ],
            summary: $count === 0
                ? 'You have no open or in-progress tickets.'
                : sprintf('You have %d open/in-progress ticket(s).', $count),
            moduleKey: 'ticketing',
            relatedRoutes: ['/ticketing?mine=1'],
            rowCount: $count,
        );
    }
}
