<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;

final class TicketingSlaRunnerService
{
    public function __construct(
        private readonly TicketingSlaCalculator $sla,
        private readonly TicketingInAppNotificationService $inApp,
        private readonly TicketingWebhookDispatcher $webhooks,
    ) {}

    /**
     * @return array{reminders: int, escalations: int}
     */
    public function run(): array
    {
        if (! $this->sla->isEnabled()) {
            return ['reminders' => 0, 'escalations' => 0];
        }

        $reminders = 0;
        $escalations = 0;

        $active = TicketingTicket::query()
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->get();

        foreach ($active as $ticket) {
            if ($ticket->created_at === null) {
                continue;
            }

            $responseAt = $ticket->created_at->copy()->addMinutes($this->sla->responseMinutesFor((string) $ticket->priority));
            if (
                $ticket->sla_reminder_sent_at === null
                && now()->greaterThanOrEqualTo($responseAt)
            ) {
                $this->sendReminder($ticket);
                $ticket->sla_reminder_sent_at = now();
                $ticket->sla_due_at = $this->sla->dueAt($ticket);
                $ticket->save();
                $reminders++;
            }

            $escalationAt = $ticket->created_at->copy()->addMinutes($this->sla->escalationMinutesFor((string) $ticket->priority));
            if (
                $ticket->sla_escalated_at === null
                && now()->greaterThanOrEqualTo($escalationAt)
            ) {
                $this->sendEscalation($ticket);
                $ticket->sla_escalated_at = now();
                $ticket->sla_due_at = $this->sla->dueAt($ticket);
                $ticket->save();
                $escalations++;
            }
        }

        return ['reminders' => $reminders, 'escalations' => $escalations];
    }

    private function sendReminder(TicketingTicket $ticket): void
    {
        $message = __('SLA reminder: ticket :number is awaiting response.', [
            'number' => $ticket->displayNumber(),
        ]);

        $this->inApp->notifyUsers(
            $this->resolverUsers($ticket),
            'ticket_sla_reminder',
            $ticket,
            $message,
        );

        $this->webhooks->dispatchIfEnabled(
            $ticket,
            'sla_reminder',
            __('SLA reminder — :number', ['number' => $ticket->displayNumber()]),
        );
    }

    private function sendEscalation(TicketingTicket $ticket): void
    {
        $message = __('SLA escalation: ticket :number exceeded response time.', [
            'number' => $ticket->displayNumber(),
        ]);

        $recipients = array_merge(
            $this->resolverUsers($ticket),
            $this->adminUsers(),
        );

        $this->inApp->notifyUsers(
            $recipients,
            'ticket_sla_escalation',
            $ticket,
            $message,
        );

        $this->webhooks->dispatchIfEnabled(
            $ticket,
            'sla_escalation',
            __('SLA escalation — :number', ['number' => $ticket->displayNumber()]),
        );
    }

    /**
     * @return list<TenantUser>
     */
    private function resolverUsers(TicketingTicket $ticket): array
    {
        $users = TenantUser::query()
            ->where('is_active', true)
            ->get()
            ->filter(static fn (TenantUser $user): bool => $user->can('ticketing:tickets:manage'))
            ->values()
            ->all();

        if ($ticket->assignee_id !== null) {
            $assignee = TenantUser::query()->find($ticket->assignee_id);
            if ($assignee instanceof TenantUser && $assignee->is_active) {
                $ids = array_map(static fn (TenantUser $u): string => (string) $u->id, $users);
                if (! in_array((string) $assignee->id, $ids, true)) {
                    $users[] = $assignee;
                }
            }
        }

        return $users;
    }

    /**
     * @return list<TenantUser>
     */
    private function adminUsers(): array
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->whereHas('roles', static fn ($q) => $q->where('name', 'tenant_admin'))
            ->get()
            ->all();
    }
}
