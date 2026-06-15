<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalAttachment extends Model
{
    use HasUuids;

    protected $table = 'e_approval_attachments';

    protected $fillable = [
        'submission_id',
        'field_name',
        'file_path',
        'file_name',
    ];

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
