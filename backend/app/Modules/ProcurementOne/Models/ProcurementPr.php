<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementPr extends Model
{
    use HasUuids;

    protected $table = 'procurement_prs';

    protected $fillable = [
        'document_no',
        'status',
        'e_approval_submission_id',
        'e_approval_form_id',
        'requestor_id',
        'title',
        'department',
        'urgency',
        'justification',
        'estimated_total',
        'currency',
        'project_id',
        'rollout_id',
        'site_id',
        'boq_line_id',
        'committed_po_amount',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'voided_at',
        'void_reason',
        'voided_by_id',
        'lifecycle_reason',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'estimated_total' => 'decimal:2',
            'committed_po_amount' => 'decimal:2',
            'metadata_json' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }

    /** @return HasMany<ProcurementPrLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementPrLine::class, 'pr_id')->orderBy('line_order');
    }

    /** @return HasMany<ProcurementPrAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProcurementPrAttachment::class, 'pr_id')->orderByDesc('created_at');
    }

    /** @return BelongsToMany<ProcurementPo, $this> */
    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(ProcurementPo::class, 'procurement_po_pr_links', 'pr_id', 'po_id')
            ->withPivot(['allocated_amount'])
            ->withTimestamps();
    }
}
