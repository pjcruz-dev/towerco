<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;

/**
 * Auto-closes resolved tickets after a tenant-configured grace window.
 * Once closed, reopen is blocked (see TicketingTicketService).
 */
final class TicketingAutoCloseService
{
    public function __construct(
        private readonly TicketingSettingsService $settings,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return array{closed: int, days: int}
     */
    public function run(): array
    {
        $days = $this->settings->autoCloseResolvedAfterDays();
        if ($days <= 0) {
            return ['closed' => 0, 'days' => $days];
        }

        $cutoff = now()->subDays($days);
        $closed = 0;

        TicketingTicket::query()
            ->where('status', TicketingTicket::STATUS_RESOLVED)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use (&$closed): void {
                foreach ($tickets as $ticket) {
                    /** @var TicketingTicket $ticket */
                    $previousStatus = (string) $ticket->status;
                    $ticket->update([
                        'status' => TicketingTicket::STATUS_CLOSED,
                        'closed_at' => now(),
                    ]);

                    $this->activity->record(
                        module: 'ticketing',
                        action: 'ticket.auto_closed',
                        summary: 'Auto-closed after resolve grace · '.$ticket->displayNumber(),
                        entityType: 'ticket',
                        entityId: (string) $ticket->id,
                        entityLabel: $ticket->displayNumber(),
                        actor: null,
                        changes: WorkspaceAuditChanges::of([
                            'status' => [
                                'from' => $previousStatus,
                                'to' => TicketingTicket::STATUS_CLOSED,
                            ],
                        ]),
                    );

                    $closed++;
                }
            });

        return ['closed' => $closed, 'days' => $days];
    }
}
