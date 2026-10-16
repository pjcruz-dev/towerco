<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingComment;
use App\Models\TicketingTicket;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\DynRecordService;

/**
 * Copies helpdesk rows into dynamic records. A second run skips ids already copied.
 */
final class TicketingHelpdeskMigrator
{
    public function __construct(
        private readonly DynRecordService $records,
    ) {}

    public function migrate(DynEntity $tickets, DynEntity $activities): void
    {
        TicketingTicket::query()
            ->with(['requester:id,name', 'assignee:id,name', 'comments.author:id,name', 'attachments'])
            ->orderBy('ticket_number')
            ->each(function (TicketingTicket $ticket) use ($tickets, $activities): void {
                $this->copyTicket($tickets, $ticket);
                foreach ($ticket->comments as $comment) {
                    $this->copyComment($activities, $ticket, $comment);
                }
            });
    }

    private function copyTicket(DynEntity $entity, TicketingTicket $ticket): void
    {
        $existing = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where(function ($query) use ($ticket): void {
                $query->whereKey($ticket->id)->orWhere('source_external_id', $ticket->id);
            })
            ->first();
        if ($existing instanceof DynRecord) {
            return;
        }

        $names = $ticket->attachments
            ->pluck('file_name')
            ->filter(static fn ($name): bool => is_string($name) && $name !== '')
            ->implode("\n");

        $record = new DynRecord;
        $record->id = (string) $ticket->id;
        $record->forceFill([
            'entity_id' => $entity->id,
            'status' => (string) $ticket->status,
            'title' => (string) $ticket->title,
            'values_json' => [
                'ticket_number' => $ticket->displayNumber(),
                'title' => (string) $ticket->title,
                'description' => $ticket->description,
                'status' => (string) $ticket->status,
                'priority' => (string) $ticket->priority,
                'category' => $ticket->category,
                'requester' => $ticket->requester?->name,
                'assigned_to' => $ticket->assignee?->name,
                'sla_status' => $ticket->sla_status,
                'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'source_label' => $ticket->source_label,
                'attachment_names' => $names !== '' ? $names : null,
            ],
            'assigned_user_id' => $ticket->assignee_id,
            'source_external_id' => (string) $ticket->id,
            'is_deleted' => false,
            'created_by' => $ticket->requester_id,
            'updated_by' => $ticket->requester_id,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ]);
        $record->timestamps = false;
        $record->save();
        $this->records->rebuildIndexes($entity, $record);
    }

    private function copyComment(DynEntity $entity, TicketingTicket $ticket, TicketingComment $comment): void
    {
        $existing = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where(function ($query) use ($comment): void {
                $query->whereKey($comment->id)->orWhere('source_external_id', $comment->id);
            })
            ->first();
        if ($existing instanceof DynRecord) {
            return;
        }

        $record = new DynRecord;
        $record->id = (string) $comment->id;
        $record->forceFill([
            'entity_id' => $entity->id,
            'status' => $comment->is_internal ? 'Internal' : 'Comment',
            'title' => mb_substr((string) $comment->body, 0, 120),
            'values_json' => [
                'helpdesk_ticket_id' => (string) $ticket->id,
                'activity_type' => $comment->is_internal ? 'Internal' : 'Comment',
                'notes' => (string) $comment->body,
                'activity_at' => $comment->created_at?->toIso8601String(),
                'performed_by' => $comment->author?->name,
            ],
            'source_external_id' => (string) $comment->id,
            'is_deleted' => false,
            'created_by' => $comment->author_id,
            'updated_by' => $comment->author_id,
            'created_at' => $comment->created_at,
            'updated_at' => $comment->updated_at,
        ]);
        $record->timestamps = false;
        $record->save();
        $this->records->rebuildIndexes($entity, $record);
    }
}
