<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketingComment extends Model
{
    use HasUuids;

    protected $table = 'ticketing_comments';

    protected $fillable = [
        'ticket_id',
        'author_id',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketingTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'author_id');
    }
}
