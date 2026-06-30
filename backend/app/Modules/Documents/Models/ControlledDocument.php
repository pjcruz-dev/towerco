<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ControlledDocument extends Model
{
    use HasUuids;

    protected $table = 'controlled_documents';

    protected $connection = 'tenant';

    protected $fillable = [
        'document_code',
        'title',
        'document_type',
        'department',
        'current_revision',
        'status',
        'effective_date',
        'next_review_date',
        'e_approval_form_id',
        'created_by_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'current_revision' => 'integer',
            'effective_date' => 'date',
            'next_review_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<ControlledDocumentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ControlledDocumentRevision::class, 'controlled_document_id');
    }

    /** @return BelongsTo<EApprovalForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EApprovalForm::class, 'e_approval_form_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_id');
    }
}
