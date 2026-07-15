<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPrAttachment extends Model
{
    use HasUuids;

    protected $table = 'procurement_pr_attachments';

    protected $fillable = [
        'pr_id',
        'e_approval_attachment_id',
        'field_name',
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

    /** @return BelongsTo<ProcurementPr, $this> */
    public function pr(): BelongsTo
    {
        return $this->belongsTo(ProcurementPr::class, 'pr_id');
    }
}
