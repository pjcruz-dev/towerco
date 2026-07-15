<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementCreditNote extends Model
{
    use HasUuids;

    protected $table = 'procurement_credit_notes';

    protected $fillable = [
        'document_no',
        'status',
        'ap_invoice_id',
        'po_id',
        'vendor_credit_note_no',
        'credit_date',
        'amount',
        'reason',
        'created_by_id',
        'approved_at',
        'approved_by_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'credit_date' => 'date',
            'amount' => 'decimal:2',
            'metadata_json' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProcurementApInvoice, $this> */
    public function apInvoice(): BelongsTo
    {
        return $this->belongsTo(ProcurementApInvoice::class, 'ap_invoice_id');
    }

    /** @return BelongsTo<ProcurementPo, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPo::class, 'po_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_id');
    }
}
