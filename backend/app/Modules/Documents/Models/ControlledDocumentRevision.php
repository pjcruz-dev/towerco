<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlledDocumentRevision extends Model
{
    use HasUuids;

    protected $table = 'controlled_document_revisions';

    protected $connection = 'tenant';

    protected $fillable = [
        'controlled_document_id',
        'revision_number',
        'change_summary',
        'e_approval_submission_id',
        'stored_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'status',
        'effective_date',
        'approved_by_id',
        'approved_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'size_bytes' => 'integer',
            'effective_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ControlledDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ControlledDocument::class, 'controlled_document_id');
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'e_approval_submission_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approved_by_id');
    }
}
