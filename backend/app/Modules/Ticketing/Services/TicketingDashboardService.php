<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;

final class TicketingDashboardService
{
    /**
     * @return array{
     *   kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   recent_tickets: list<array<string, mixed>>,
     *   message: string
     * }
     */
    public function build(TenantUser $user): array
    {
        $canManage = $user->can('ticketing:tickets:manage');
        $userId = (string) $user->id;

        $openCount = TicketingTicket::query()
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->when(! $canManage, fn ($q) => $q->where(function ($inner) use ($userId): void {
                $inner->where('requester_id', $userId)->orWhere('assignee_id', $userId);
            }))
            ->count();

        $assignedToMe = TicketingTicket::query()
            ->where('assignee_id', $userId)
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->count();

        $urgentCount = TicketingTicket::query()
            ->where('priority', TicketingTicket::PRIORITY_URGENT)
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->when(! $canManage, fn ($q) => $q->where(function ($inner) use ($userId): void {
                $inner->where('requester_id', $userId)->orWhere('assignee_id', $userId);
            }))
            ->count();

        $resolvedThisWeek = TicketingTicket::query()
            ->where('status', TicketingTicket::STATUS_RESOLVED)
            ->where('resolved_at', '>=', now()->subDays(7))
            ->when(! $canManage, fn ($q) => $q->where(function ($inner) use ($userId): void {
                $inner->where('requester_id', $userId)->orWhere('assignee_id', $userId);
            }))
            ->count();

        $slaAtRisk = 0;
        if ($canManage) {
            $slaCalculator = app(TicketingSlaCalculator::class);
            $slaAtRisk = TicketingTicket::query()
                ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
                ->get()
                ->filter(static fn (TicketingTicket $ticket): bool => in_array(
                    $slaCalculator->statusFor($ticket),
                    ['at_risk', 'breached'],
                    true,
                ))
                ->count();
        }

        $recent = TicketingTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email'])
            ->when(! $canManage, fn ($q) => $q->where(function ($inner) use ($userId): void {
                $inner->where('requester_id', $userId)->orWhere('assignee_id', $userId);
            }))
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (TicketingTicket $ticket) => $this->ticketSummary($ticket))
            ->all();

        return [
            'kpis' => [
                ['key' => 'open', 'label' => 'Open / in progress', 'value' => $openCount, 'tone' => 'neutral'],
                ['key' => 'assigned_me', 'label' => 'Assigned to me', 'value' => $assignedToMe, 'tone' => 'warning'],
                ['key' => 'urgent', 'label' => 'Urgent', 'value' => $urgentCount, 'tone' => 'danger'],
                ...($canManage ? [['key' => 'sla_at_risk', 'label' => 'SLA at risk', 'value' => $slaAtRisk, 'tone' => 'warning']] : []),
                ['key' => 'resolved_week', 'label' => 'Resolved (7d)', 'value' => $resolvedThisWeek, 'tone' => 'success'],
            ],
            'recent_tickets' => $recent,
            'message' => 'Cross-module issue tracking — raise tickets from any TowerOS module or manually.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketSummary(TicketingTicket $ticket): array
    {
        return [
            'id' => (string) $ticket->id,
            'ticket_number' => $ticket->displayNumber(),
            'title' => $ticket->title,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'source_module' => $ticket->source_module,
            'requester' => $ticket->requester ? [
                'id' => (string) $ticket->requester->id,
                'name' => $ticket->requester->name,
            ] : null,
            'assignee' => $ticket->assignee ? [
                'id' => (string) $ticket->assignee->id,
                'name' => $ticket->assignee->name,
            ] : null,
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
