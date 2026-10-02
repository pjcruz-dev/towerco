<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlledDocumentReviewAlert extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'controlled_document_review_alerts';

    protected $fillable = [
        'controlled_document_id',
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

    /** @return BelongsTo<ControlledDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ControlledDocument::class, 'controlled_document_id');
    }
}
