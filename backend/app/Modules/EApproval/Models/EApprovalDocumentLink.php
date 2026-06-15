<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalDocumentLink extends Model
{
    use HasUuids;

    protected $table = 'e_approval_document_links';

    protected $fillable = [
        'source_submission_id',
        'target_submission_id',
        'link_type',
        'created_by',
    ];

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'source_submission_id');
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function targetSubmission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'target_submission_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by');
    }
}
