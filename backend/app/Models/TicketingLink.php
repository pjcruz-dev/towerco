<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketingLink extends Model
{
    use HasUuids;

    protected $table = 'ticketing_links';

    protected $fillable = [
        'ticket_id',
        'link_module',
        'link_type',
        'link_id',
        'link_label',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketingTicket::class, 'ticket_id');
    }
}
