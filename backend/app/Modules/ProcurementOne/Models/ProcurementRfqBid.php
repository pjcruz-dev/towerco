<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRfqBid extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_bids';

    protected $fillable = [
        'rfq_id',
        'vendor_id',
        'status',
        'total_amount',
        'total_amount_monthly',
        'total_amount_yearly',
        'normalized_annual_amount',
        'currency_code',
        'validity_until',
        'avg_lead_time_days',
        'notes',
        'captured_by_id',
        'submitted_at',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_amount_monthly' => 'decimal:2',
            'total_amount_yearly' => 'decimal:2',
            'normalized_annual_amount' => 'decimal:2',
            'validity_until' => 'date',
            'submitted_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'rfq_id');
    }

    /** @return BelongsTo<ProcurementVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ProcurementVendor::class, 'vendor_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'captured_by_id');
    }

    /** @return HasMany<ProcurementRfqBidLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementRfqBidLine::class, 'bid_id');
    }

    /** @return HasMany<ProcurementRfqBidVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProcurementRfqBidVersion::class, 'bid_id')->orderByDesc('version_no');
    }

    /** @return HasMany<ProcurementRfqBidAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProcurementRfqBidAttachment::class, 'bid_id');
    }
}
