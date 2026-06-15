<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalSubmissionFollowup extends Model
{
    use HasUuids;

    protected $table = 'e_approval_submission_followups';

    protected $fillable = ['submission_id', 'requestor_id', 'approver_id', 'message'];

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approver_id');
    }
}
