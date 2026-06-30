<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementApInvoice extends Model
{
    use HasUuids;

    protected $table = 'procurement_ap_invoices';

    protected $fillable = [
        'document_no',
        'status',
        'po_id',
        'grn_id',
        'vendor_code',
        'vendor_name',
        'vendor_invoice_no',
        'invoice_date',
        'due_date',
        'payment_terms',
        'currency_code',
        'exchange_rate',
        'vatable_amount',
        'vat_exempt_amount',
        'zero_rated_amount',
        'vat_rate',
        'vat_amount',
        'total_vat_inclusive',
        'less_discount',
        'grand_total',
        'match_mode',
        'match_status',
        'match_variance_amount',
        'e_approval_submission_id',
        'e_approval_form_id',
        'requestor_id',
        'submitted_at',
        'approved_at',
        'cancelled_at',
        'voided_at',
        'void_reason',
        'voided_by_id',
        'notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'vatable_amount' => 'decimal:2',
            'vat_exempt_amount' => 'decimal:2',
            'zero_rated_amount' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'total_vat_inclusive' => 'decimal:2',
            'less_discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'match_variance_amount' => 'decimal:2',
            'metadata_json' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProcurementPo, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPo::class, 'po_id');
    }

    /** @return BelongsTo<ProcurementGrn, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(ProcurementGrn::class, 'grn_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }

    /** @return HasMany<ProcurementApInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementApInvoiceLine::class, 'ap_invoice_id')->orderBy('line_order');
    }

    /** @return HasMany<ProcurementCreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(ProcurementCreditNote::class, 'ap_invoice_id')->orderByDesc('created_at');
    }

    /** @return HasMany<ProcurementPaymentRequest, $this> */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(ProcurementPaymentRequest::class, 'ap_invoice_id')->orderByDesc('created_at');
    }
}
