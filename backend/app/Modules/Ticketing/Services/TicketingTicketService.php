<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Core\Support\AllowlistedSort;
use App\Models\TicketingComment;
use App\Models\TicketingLink;
use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use App\Modules\Ticketing\Support\TicketingSourceCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TicketingTicketService
{
    private const SORTABLE = [
        'ticket_number',
        'title',
        'status',
        'priority',
        'updated_at',
        'created_at',
        'sla_due_at',
    ];

    public function __construct(
        private readonly TicketingSourceCatalog $sources,
        private readonly TicketingCategoryCatalog $categories,
        private readonly TicketingSlaCalculator $sla,
    ) {}

  /**
   * @param  array{
   *   page?: int,
   *   per_page?: int,
   *   search?: string|null,
   *   status?: string|null,
   *   priority?: string|null,
   *   assignee_id?: string|null,
   *   source_module?: string|null,
   *   source_reference_id?: string|null,
   *   mine?: bool,
   *   sort?: string|null
   * }  $query
   */
    public function paginate(TenantUser $viewer, array $query): LengthAwarePaginator
    {
        $canManage = $viewer->can('ticketing:tickets:manage');
        $search = isset($query['search']) ? trim((string) $query['search']) : '';
        $status = isset($query['status']) ? trim((string) $query['status']) : '';
        $priority = isset($query['priority']) ? trim((string) $query['priority']) : '';
        $assigneeId = isset($query['assignee_id']) ? trim((string) $query['assignee_id']) : '';
        $sourceModule = isset($query['source_module']) ? trim((string) $query['source_module']) : '';
        $sourceReferenceId = isset($query['source_reference_id']) ? trim((string) $query['source_reference_id']) : '';
        $mine = (bool) ($query['mine'] ?? false);

        $builder = TicketingTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($assigneeId !== '', fn ($q) => $q->where('assignee_id', $assigneeId))
            ->when($sourceModule !== '', fn ($q) => $q->where('source_module', $sourceModule))
            ->when($sourceReferenceId !== '', fn ($q) => $q->where('source_reference_id', $sourceReferenceId))
            ->when($mine, fn ($q) => $q->where('requester_id', $viewer->id))
            ->when(! $canManage && ! $mine, fn ($q) => $q->where('requester_id', $viewer->id))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('ticket_number', 'like', '%'.ltrim($search, 'TKT-').'%');
                });
            });

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($query['sort'] ?? 'updated_at:desc'),
            self::SORTABLE,
            'updated_at',
            'desc',
        );
        $builder->orderBy($column, $direction);

        return $builder->paginate(
            max(1, min(100, (int) ($query['per_page'] ?? 20))),
            ['*'],
            'page',
            max(1, (int) ($query['page'] ?? 1)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function asListRow(TicketingTicket $ticket): array
    {
        return [
            'id' => (string) $ticket->id,
            'ticket_number' => $ticket->displayNumber(),
            'title' => $ticket->title,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'source_module' => $ticket->source_module,
            'source_label' => $ticket->source_label,
            'requester' => $ticket->requester ? [
                'id' => (string) $ticket->requester->id,
                'name' => $ticket->requester->name,
                'email' => $ticket->requester->email,
            ] : null,
            'assignee' => $ticket->assignee ? [
                'id' => (string) $ticket->assignee->id,
                'name' => $ticket->assignee->name,
                'email' => $ticket->assignee->email,
            ] : null,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'sla_status' => $this->sla->statusFor($ticket),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asDetail(TicketingTicket $ticket, ?TenantUser $viewer = null): array
    {
        $ticket->load([
            'requester:id,name,email',
            'assignee:id,name,email',
            'comments' => fn ($q) => $q->with('author:id,name,email')->orderBy('created_at'),
            'attachments' => fn ($q) => $q->with('uploadedBy:id,name')->orderBy('created_at'),
            'links',
        ]);

        $canManage = $viewer?->can('ticketing:tickets:manage') ?? true;
        $comments = $ticket->comments;
        if ($viewer !== null && ! $canManage) {
            $comments = $comments->where('is_internal', false)->values();
        }

        return [
            ...$this->asListRow($ticket),
            'description' => $ticket->description,
            'source_reference_type' => $ticket->source_reference_type,
            'source_reference_id' => $ticket->source_reference_id,
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'can_reopen' => $this->canReopen($ticket, $viewer),
            'comments' => $comments->map(fn (TicketingComment $comment) => [
                'id' => (string) $comment->id,
                'body' => $comment->body,
                'is_internal' => $comment->is_internal,
                'author' => $comment->author ? [
                    'id' => (string) $comment->author->id,
                    'name' => $comment->author->name,
                ] : null,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all(),
            'attachments' => $ticket->attachments->map(fn ($attachment) => [
                'id' => (string) $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'uploaded_by' => $attachment->uploadedBy ? [
                    'id' => (string) $attachment->uploadedBy->id,
                    'name' => $attachment->uploadedBy->name,
                ] : null,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ])->all(),
            'links' => $ticket->links->map(fn (TicketingLink $link) => [
                'id' => (string) $link->id,
                'link_module' => $link->link_module,
                'link_type' => $link->link_type,
                'link_id' => $link->link_id,
                'link_label' => $link->link_label,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(TenantUser $requester, array $data): TicketingTicket
    {
        $sourceModule = (string) ($data['source_module'] ?? TicketingSourceCatalog::MODULE_MANUAL);
        if (! $this->sources->isKnownModule($sourceModule)) {
            throw ValidationException::withMessages([
                'source_module' => [__('Unknown source module.')],
            ]);
        }

        $category = $data['category'] ?? null;
        if ($category !== null && ! $this->categories->isValid((string) $category)) {
            throw ValidationException::withMessages([
                'category' => [__('Invalid ticket category.')],
            ]);
        }

        return DB::transaction(function () use ($requester, $data, $sourceModule, $category): TicketingTicket {
            $ticket = TicketingTicket::query()->create([
                'id' => (string) Str::uuid(),
                'ticket_number' => $this->nextTicketNumber(),
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'status' => TicketingTicket::STATUS_OPEN,
                'priority' => TicketingTicket::PRIORITY_NORMAL,
                'category' => $category,
                'source_module' => $sourceModule,
                'source_reference_type' => $data['source_reference_type'] ?? null,
                'source_reference_id' => $data['source_reference_id'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'requester_id' => $requester->id,
                'assignee_id' => $data['assignee_id'] ?? null,
            ]);

            foreach ($data['links'] ?? [] as $link) {
                if (! is_array($link)) {
                    continue;
                }

                TicketingLink::query()->create([
                    'id' => (string) Str::uuid(),
                    'ticket_id' => $ticket->id,
                    'link_module' => (string) ($link['link_module'] ?? $sourceModule),
                    'link_type' => (string) ($link['link_type'] ?? 'reference'),
                    'link_id' => (string) ($link['link_id'] ?? ''),
                    'link_label' => $link['link_label'] ?? null,
                ]);
            }

            $this->syncSla($ticket, true);

            return $ticket->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   ticket: TicketingTicket,
     *   lifecycle_event: ?string,
     *   resolution_comment: ?string,
     *   assignee_changed: bool,
     *   new_assignee: ?TenantUser
     * }
     */
    public function update(TicketingTicket $ticket, TenantUser $actor, array $data): array
    {
        $canManage = $actor->can('ticketing:tickets:manage');
        $isRequester = (string) $ticket->requester_id === (string) $actor->id;

        if (! $canManage && ! $isRequester) {
            abort(403);
        }

        $lifecycleEvent = null;
        $resolutionComment = null;
        $assigneeChanged = false;
        $newAssignee = null;
        $previousAssigneeId = $ticket->assignee_id !== null ? (string) $ticket->assignee_id : null;
        $updates = [];

        if (array_key_exists('title', $data) && ($canManage || $isRequester)) {
            $updates['title'] = (string) $data['title'];
        }

        if (array_key_exists('description', $data) && ($canManage || $isRequester)) {
            $updates['description'] = $data['description'];
        }

        $priorityChanged = false;
        if (array_key_exists('priority', $data) && $canManage) {
            $nextPriority = (string) $data['priority'];
            if ($nextPriority !== (string) $ticket->priority) {
                $priorityChanged = true;
            }
            $updates['priority'] = $nextPriority;
        }

        if (array_key_exists('category', $data) && $canManage) {
            $nextCategory = $data['category'];
            if ($nextCategory !== null && ! $this->categories->isValid((string) $nextCategory)) {
                throw ValidationException::withMessages([
                    'category' => [__('Invalid ticket category.')],
                ]);
            }
            $updates['category'] = $nextCategory;
        }

        if (array_key_exists('assignee_id', $data) && $canManage) {
            $nextAssigneeId = $data['assignee_id'] !== null && $data['assignee_id'] !== ''
                ? (string) $data['assignee_id']
                : null;
            if ($nextAssigneeId !== $previousAssigneeId) {
                $assigneeChanged = $nextAssigneeId !== null;
            }
            $updates['assignee_id'] = $nextAssigneeId;
        }

        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            $previousStatus = (string) $ticket->status;

            if ($status === TicketingTicket::STATUS_OPEN && $isRequester && ! $canManage) {
                if (! in_array($previousStatus, [TicketingTicket::STATUS_RESOLVED, TicketingTicket::STATUS_CLOSED], true)) {
                    throw ValidationException::withMessages([
                        'status' => [__('Only resolved or closed tickets can be reopened.')],
                    ]);
                }
                $updates['status'] = TicketingTicket::STATUS_OPEN;
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
                $lifecycleEvent = 'reopened';
            } elseif ($canManage) {
                if ($status === TicketingTicket::STATUS_RESOLVED && $previousStatus !== TicketingTicket::STATUS_RESOLVED) {
                    $resolutionComment = trim((string) ($data['resolution_comment'] ?? ''));
                    if ($resolutionComment === '') {
                        throw ValidationException::withMessages([
                            'resolution_comment' => [__('A resolution comment is required when resolving a ticket.')],
                        ]);
                    }
                    $lifecycleEvent = 'resolved';
                }

                $updates['status'] = $status;
                if ($status === TicketingTicket::STATUS_RESOLVED) {
                    $updates['resolved_at'] = now();
                } elseif ($status === TicketingTicket::STATUS_CLOSED) {
                    $updates['closed_at'] = now();
                } elseif ($status === TicketingTicket::STATUS_OPEN) {
                    $updates['resolved_at'] = null;
                    $updates['closed_at'] = null;
                    if ($previousStatus !== TicketingTicket::STATUS_OPEN) {
                        $lifecycleEvent = 'reopened';
                    }
                }
            } elseif ($isRequester && $status === TicketingTicket::STATUS_CLOSED) {
                $updates['status'] = TicketingTicket::STATUS_CLOSED;
                $updates['closed_at'] = now();
            }
        }

        if ($updates !== []) {
            $ticket->update($updates);
            $ticket = $ticket->fresh();
            if ($priorityChanged) {
                $this->syncSla($ticket, true);
            }
        }

        if ($lifecycleEvent === 'resolved' && $resolutionComment !== null) {
            $this->addComment($ticket, $actor, $resolutionComment, false);
        }

        if ($assigneeChanged && $ticket->assignee_id !== null) {
            $newAssignee = TenantUser::query()->find($ticket->assignee_id);
        }

        return [
            'ticket' => $ticket,
            'lifecycle_event' => $lifecycleEvent,
            'resolution_comment' => $resolutionComment,
            'assignee_changed' => $assigneeChanged,
            'new_assignee' => $newAssignee,
        ];
    }

    public function addComment(TicketingTicket $ticket, TenantUser $author, string $body, bool $isInternal = false): TicketingComment
    {
        if ($isInternal && ! $author->can('ticketing:tickets:manage')) {
            abort(403);
        }

        return TicketingComment::query()->create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'author_id' => $author->id,
            'body' => $body,
            'is_internal' => $isInternal,
        ]);
    }

    public function assertCanView(TicketingTicket $ticket, TenantUser $user): void
    {
        if ($user->can('ticketing:tickets:manage')) {
            return;
        }

        if ((string) $ticket->requester_id === (string) $user->id) {
            return;
        }

        abort(403);
    }

    public function canReopen(TicketingTicket $ticket, ?TenantUser $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        $isRequester = (string) $ticket->requester_id === (string) $viewer->id;

        return $isRequester && in_array($ticket->status, [
            TicketingTicket::STATUS_RESOLVED,
            TicketingTicket::STATUS_CLOSED,
        ], true);
    }

    private function syncSla(TicketingTicket $ticket, bool $resetFlags = false): void
    {
        $dueAt = $this->sla->dueAt($ticket);
        $payload = ['sla_due_at' => $dueAt];
        if ($resetFlags) {
            $payload['sla_reminder_sent_at'] = null;
            $payload['sla_escalated_at'] = null;
        }
        $ticket->update($payload);
    }

    private function nextTicketNumber(): int
    {
        $max = (int) TicketingTicket::query()->max('ticket_number');

        return $max + 1;
    }
}
