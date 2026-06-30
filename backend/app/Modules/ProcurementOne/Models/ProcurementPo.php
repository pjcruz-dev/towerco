<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementPo extends Model
{
    use HasUuids;

    protected $table = 'procurement_pos';

    protected $fillable = [
        'document_no',
        'status',
        'e_approval_submission_id',
        'e_approval_form_id',
        'requestor_id',
        'contract_id',
        'vendor_code',
        'vendor_name',
        'supplier',
        'ship_to',
        'delivery_date',
        'payment_terms',
        'currency_code',
        'exchange_rate',
        'delivery_location',
        'vatable_amount',
        'vat_exempt_amount',
        'zero_rated_amount',
        'vat_rate',
        'vat_amount',
        'total_vat_inclusive',
        'less_discount',
        'grand_total',
        'total_amount',
        'submitted_at',
        'approved_at',
        'sent_at',
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
            'delivery_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'vatable_amount' => 'decimal:2',
            'vat_exempt_amount' => 'decimal:2',
            'zero_rated_amount' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'total_vat_inclusive' => 'decimal:2',
            'less_discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'metadata_json' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }

    /** @return HasMany<ProcurementPoLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementPoLine::class, 'po_id')->orderBy('line_order');
    }

    /** @return HasMany<ProcurementPoPrLink, $this> */
    public function prLinks(): HasMany
    {
        return $this->hasMany(ProcurementPoPrLink::class, 'po_id');
    }

    /** @return HasMany<ProcurementGrn, $this> */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(ProcurementGrn::class, 'po_id')->orderByDesc('created_at');
    }

    /** @return HasMany<ProcurementApInvoice, $this> */
    public function apInvoices(): HasMany
    {
        return $this->hasMany(ProcurementApInvoice::class, 'po_id')->orderByDesc('created_at');
    }

    /** @return HasMany<ProcurementCreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(ProcurementCreditNote::class, 'po_id')->orderByDesc('created_at');
    }

    /** @return BelongsToMany<ProcurementPr, $this> */
    public function purchaseRequisitions(): BelongsToMany
    {
        return $this->belongsToMany(ProcurementPr::class, 'procurement_po_pr_links', 'po_id', 'pr_id')
            ->withPivot(['allocated_amount'])
            ->withTimestamps();
    }

    /** @return BelongsTo<ProcurementContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ProcurementContract::class, 'contract_id');
    }
}
