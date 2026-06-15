<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketingAttachment extends Model
{
    use HasUuids;

    protected $table = 'ticketing_attachments';

    protected $fillable = [
        'ticket_id',
        'uploaded_by_id',
        'file_path',
        'file_name',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketingTicket::class, 'ticket_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_id');
    }
}
