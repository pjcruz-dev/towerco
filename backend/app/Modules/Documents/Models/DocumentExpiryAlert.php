<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExpiryAlert extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'document_expiry_alerts';

    protected $fillable = [
        'document_id',
        'window_days',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'window_days' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
