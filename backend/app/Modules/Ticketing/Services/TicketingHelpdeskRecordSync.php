<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingComment;
use App\Models\TicketingTicket;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\ManagedSeriesService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps ticketing_tickets in step with helpdesk dynamic records so the SLA
 * clock, resolve comment, reopen, and IT mailbox keep running.
 */
final class TicketingHelpdeskRecordSync
{
    private static bool $syncing = false;

    public function __construct(
        private readonly TicketingTicketService $tickets,
        private readonly TicketingNotificationDispatcher $notifications,
        private readonly TicketingSlaCalculator $sla,
        private readonly TicketingCategoryCatalog $categories,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function guard(DynEntity $entity, ?DynRecord $record, array $values, TenantUser $actor): void
    {
        if ($entity->slug !== TicketingSchemaService::SLUG || $record === null) {
            return;
        }

        $ticket = $this->findTicket($record);
        if (! $ticket instanceof TicketingTicket) {
            return;
        }

        $next = $this->normalizeStatus((string) ($values['status'] ?? $record->status ?? ''));
        $previous = (string) $ticket->status;
        if ($next === '' || $next === $previous) {
            return;
        }

        if ($next === TicketingTicket::STATUS_OPEN && $previous === TicketingTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => [__('Closed tickets cannot be reopened.')],
            ]);
        }

        if ($next === TicketingTicket::STATUS_RESOLVED && $previous !== TicketingTicket::STATUS_RESOLVED) {
            if (! $actor->can('ticketing:tickets:manage')) {
                throw ValidationException::withMessages([
                    'status' => [__('Only IT can resolve a ticket.')],
                ]);
            }
            $note = trim((string) ($values['resolution_notes'] ?? ''));
            if ($note === '') {
                throw ValidationException::withMessages([
                    'resolution_notes' => [__('A resolution comment is required when resolving a ticket.')],
                ]);
            }
        }

        if (
            $next === TicketingTicket::STATUS_OPEN
            && $previous === TicketingTicket::STATUS_RESOLVED
            && ! $actor->can('ticketing:tickets:manage')
            && (string) $ticket->requester_id !== (string) $actor->id
        ) {
            throw ValidationException::withMessages([
                'status' => [__('Only the requester can reopen this ticket.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $beforeValues
     */
    public function sync(DynEntity $entity, DynRecord $record, ?array $beforeValues, TenantUser $actor): void
    {
        if (self::$syncing) {
            return;
        }

        self::$syncing = true;
        try {
            if ($entity->slug === 'helpdesk_ticket_activities') {
                $this->syncActivity($record, $actor);

                return;
            }
            if ($entity->slug === TicketingSchemaService::SLUG) {
                $this->syncTicket($record, $actor);
            }
        } finally {
            self::$syncing = false;
        }
    }

    private function syncTicket(DynRecord $record, TenantUser $actor): void
    {
        $values = is_array($record->values_json) ? $record->values_json : [];
        $ticket = $this->findTicket($record);

        if (! $ticket instanceof TicketingTicket) {
            $ticket = $this->insertTicket($record, $values, $actor);
            $this->rememberLink($record, $ticket);
            $ticket->loadMissing('requester');
            $requester = $ticket->requester instanceof TenantUser ? $ticket->requester : $actor;
            $this->notifications->dispatchCreated($ticket, $requester);

            return;
        }

        $payload = [];
        $title = trim((string) ($values['title'] ?? $record->title ?? ''));
        if ($title !== '' && $title !== (string) $ticket->title) {
            $payload['title'] = $title;
        }
        if (array_key_exists('description', $values) && (string) ($values['description'] ?? '') !== (string) ($ticket->description ?? '')) {
            $payload['description'] = $values['description'];
        }

        $status = $this->normalizeStatus((string) ($values['status'] ?? ''));
        if ($status !== '' && $status !== (string) $ticket->status) {
            $payload['status'] = $status;
            if ($status === TicketingTicket::STATUS_RESOLVED) {
                $payload['resolution_comment'] = trim((string) ($values['resolution_notes'] ?? ''));
            }
        }

        $priority = $this->normalizePriority((string) ($values['priority'] ?? ''));
        if ($priority !== '' && $priority !== (string) $ticket->priority && $actor->can('ticketing:tickets:manage')) {
            $payload['priority'] = $priority;
        }

        $category = trim((string) ($values['category'] ?? ''));
        $currentCategory = $ticket->category !== null ? (string) $ticket->category : '';
        if ($category !== $currentCategory && ($category === '' || $this->categories->isValid($category)) && $actor->can('ticketing:tickets:manage')) {
            $payload['category'] = $category !== '' ? $category : null;
        }

        if ($actor->can('ticketing:tickets:manage')) {
            $requesterId = $this->activeUserId((string) ($values['requester'] ?? ''));
            if ($requesterId !== null && $requesterId !== (string) $ticket->requester_id) {
                $ticket->requester_id = $requesterId;
                $ticket->save();
            }
        }

        if ($actor->can('ticketing:tickets:manage') && array_key_exists('assigned_to', $values)) {
            $rawAssignee = trim((string) ($values['assigned_to'] ?? ''));
            $nextAssignee = $rawAssignee === '' ? null : $this->activeUserId($rawAssignee);
            $currentAssignee = $ticket->assignee_id !== null ? (string) $ticket->assignee_id : null;
            if (($rawAssignee === '' || $nextAssignee !== null) && $nextAssignee !== $currentAssignee) {
                $payload['assignee_id'] = $nextAssignee;
            }
        } elseif ($record->assigned_user_id !== null && (string) $record->assigned_user_id !== (string) $ticket->assignee_id && $actor->can('ticketing:tickets:manage')) {
            $payload['assignee_id'] = (string) $record->assigned_user_id;
        }

        if ($payload === []) {
            $this->rememberLink($record, $ticket);

            return;
        }

        $result = $this->tickets->update($ticket, $actor, $payload);
        $updated = $result['ticket'];
        $this->rememberLink($record, $updated);

        if ($result['lifecycle_event'] === 'resolved') {
            $this->notifications->dispatchResolved(
                $updated,
                $actor,
                (string) ($result['resolution_comment'] ?? ''),
            );
        } elseif ($result['lifecycle_event'] === 'reopened') {
            $this->notifications->dispatchReopened($updated, $actor);
        }

        if ($result['assignee_changed'] && $result['new_assignee'] instanceof TenantUser) {
            $this->notifications->dispatchAssigned($updated, $actor, $result['new_assignee']);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertTicket(DynRecord $record, array $values, TenantUser $actor): TicketingTicket
    {
        $status = $this->normalizeStatus((string) ($values['status'] ?? $record->status ?? ''));
        if (! in_array($status, [
            TicketingTicket::STATUS_OPEN,
            TicketingTicket::STATUS_IN_PROGRESS,
            TicketingTicket::STATUS_RESOLVED,
            TicketingTicket::STATUS_CLOSED,
        ], true)) {
            $status = TicketingTicket::STATUS_OPEN;
        }
        $priority = $this->normalizePriority((string) ($values['priority'] ?? ''));
        if ($priority === '') {
            $priority = TicketingTicket::PRIORITY_NORMAL;
        }
        $category = trim((string) ($values['category'] ?? ''));
        if ($category !== '' && ! $this->categories->isValid($category)) {
            $category = '';
        }

        $ticket = new TicketingTicket;
        $ticket->id = (string) $record->id;
        $ticket->forceFill([
            'ticket_number' => app(ManagedSeriesService::class)->issueTicketNumber(),
            'title' => trim((string) ($values['title'] ?? $record->title ?? 'Ticket')) ?: 'Ticket',
            'description' => $values['description'] ?? null,
            'status' => $status,
            'priority' => $priority,
            'category' => $category !== '' ? $category : null,
            'source_module' => 'manual',
            'requester_id' => $this->requesterIdForCreate($values, $actor),
            'assignee_id' => $actor->can('ticketing:tickets:manage')
                ? $this->activeUserId((string) ($values['assigned_to'] ?? ''))
                : $record->assigned_user_id,
        ]);
        $ticket->save();
        $ticket = $ticket->fresh() ?? $ticket;

        if ($this->sla->isEnabled()) {
            $ticket->sla_due_at = $this->sla->dueAt($ticket);
            $ticket->sla_status = $this->sla->statusFor($ticket);
            $ticket->save();
        }

        return $ticket->fresh() ?? $ticket;
    }

    private function rememberLink(DynRecord $record, TicketingTicket $ticket): void
    {
        $values = is_array($record->values_json) ? $record->values_json : [];
        $display = $ticket->displayNumber();
        $dirty = false;
        if ((string) ($record->source_external_id ?? '') !== (string) $ticket->id) {
            $record->source_external_id = (string) $ticket->id;
            $dirty = true;
        }
        if ((string) ($values['ticket_number'] ?? '') !== $display) {
            $values['ticket_number'] = $display;
            $record->values_json = $values;
            $dirty = true;
        }
        if ((string) ($record->status ?? '') !== (string) $ticket->status) {
            $record->status = (string) $ticket->status;
            $values['status'] = (string) $ticket->status;
            $dirty = true;
        }
        $assigneeId = $ticket->assignee_id !== null ? (string) $ticket->assignee_id : null;
        if ((string) ($record->assigned_user_id ?? '') !== (string) ($assigneeId ?? '')) {
            $record->assigned_user_id = $assigneeId;
            $dirty = true;
        }
        if ($dirty) {
            $record->values_json = $values;
            $record->save();
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function requesterIdForCreate(array $values, TenantUser $actor): string
    {
        if ($actor->can('ticketing:tickets:manage')) {
            $picked = $this->activeUserId((string) ($values['requester'] ?? ''));
            if ($picked !== null) {
                return $picked;
            }
        }

        return (string) $actor->id;
    }

    private function activeUserId(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $query = TenantUser::query()->where('is_active', true);
        if (Str::isUuid($raw)) {
            $user = $query->whereKey($raw)->first();

            return $user instanceof TenantUser ? (string) $user->id : null;
        }

        $matches = $query->where('name', $raw)->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }
        $user = $matches->first();

        return $user instanceof TenantUser ? (string) $user->id : null;
    }

    private function syncActivity(DynRecord $record, TenantUser $actor): void
    {
        if ($record->source_external_id !== null && TicketingComment::query()->whereKey($record->source_external_id)->exists()) {
            return;
        }

        $values = is_array($record->values_json) ? $record->values_json : [];
        $ticketId = (string) ($values['helpdesk_ticket_id'] ?? '');
        $body = trim((string) ($values['notes'] ?? ''));
        if ($ticketId === '' || $body === '' || ! TicketingTicket::query()->whereKey($ticketId)->exists()) {
            return;
        }

        $comment = TicketingComment::query()->create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticketId,
            'author_id' => (string) ($record->created_by ?: $actor->id),
            'body' => $body,
            'is_internal' => (string) ($values['activity_type'] ?? '') === 'Internal',
        ]);
        $record->source_external_id = (string) $comment->id;
        $record->save();
    }

    private function findTicket(DynRecord $record): ?TicketingTicket
    {
        $id = (string) ($record->source_external_id ?: $record->id);
        $ticket = TicketingTicket::query()->find($id);
        if ($ticket instanceof TicketingTicket) {
            return $ticket;
        }

        return TicketingTicket::query()->find($record->id);
    }

    private function normalizeStatus(string $status): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim($status)));

        return match ($key) {
            'open' => TicketingTicket::STATUS_OPEN,
            'in_progress', 'inprogress' => TicketingTicket::STATUS_IN_PROGRESS,
            'resolved' => TicketingTicket::STATUS_RESOLVED,
            'closed' => TicketingTicket::STATUS_CLOSED,
            default => '',
        };
    }

    private function normalizePriority(string $priority): string
    {
        $key = strtolower(trim($priority));

        return match ($key) {
            'low' => TicketingTicket::PRIORITY_LOW,
            'normal' => TicketingTicket::PRIORITY_NORMAL,
            'high' => TicketingTicket::PRIORITY_HIGH,
            'urgent' => TicketingTicket::PRIORITY_URGENT,
            default => '',
        };
    }
}
