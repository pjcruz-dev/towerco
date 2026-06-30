<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementPaymentBatch extends Model
{
    use HasUuids;

    protected $table = 'procurement_payment_batches';

    protected $fillable = [
        'document_no',
        'status',
        'scheduled_date',
        'total_amount',
        'currency_code',
        'exported_at',
        'reconciled_at',
        'created_by_id',
        'exported_by_id',
        'reconciled_by_id',
        'notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'total_amount' => 'decimal:2',
            'exported_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_id');
    }

    /** @return HasMany<ProcurementPaymentRequest, $this> */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(ProcurementPaymentRequest::class, 'payment_batch_id')->orderBy('document_no');
    }
}
