<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPaymentRequest extends Model
{
    use HasUuids;

    protected $table = 'procurement_payment_requests';

    protected $fillable = [
        'document_no',
        'status',
        'ap_invoice_id',
        'payment_batch_id',
        'vendor_code',
        'vendor_name',
        'amount',
        'currency_code',
        'scheduled_date',
        'paid_at',
        'reconciled_at',
        'payment_reference',
        'requestor_id',
        'approved_by_id',
        'approved_at',
        'paid_by_id',
        'reconciled_by_id',
        'notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'scheduled_date' => 'date',
            'paid_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'metadata_json' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProcurementApInvoice, $this> */
    public function apInvoice(): BelongsTo
    {
        return $this->belongsTo(ProcurementApInvoice::class, 'ap_invoice_id');
    }

    /** @return BelongsTo<ProcurementPaymentBatch, $this> */
    public function paymentBatch(): BelongsTo
    {
        return $this->belongsTo(ProcurementPaymentBatch::class, 'payment_batch_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }
}
