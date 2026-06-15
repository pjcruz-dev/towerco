<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketingTicket extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $table = 'ticketing_tickets';

    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'status',
        'priority',
        'category',
        'source_module',
        'source_reference_type',
        'source_reference_id',
        'source_label',
        'requester_id',
        'assignee_id',
        'resolved_at',
        'closed_at',
        'sla_due_at',
        'sla_reminder_sent_at',
        'sla_escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_number' => 'integer',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_reminder_sent_at' => 'datetime',
            'sla_escalated_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketingComment::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketingAttachment::class, 'ticket_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(TicketingLink::class, 'ticket_id');
    }

    public function displayNumber(): string
    {
        return 'TKT-'.str_pad((string) $this->ticket_number, 5, '0', STR_PAD_LEFT);
    }
}
