<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalFormValue extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'e_approval_form_values';

    protected $fillable = ['submission_id', 'field_id', 'value'];

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<EApprovalFormField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(EApprovalFormField::class, 'field_id');
    }
}
