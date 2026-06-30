<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRfqBidVersion extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_bid_versions';

    protected $fillable = [
        'bid_id',
        'version_no',
        'total_amount',
        'total_amount_monthly',
        'total_amount_yearly',
        'normalized_annual_amount',
        'currency_code',
        'validity_until',
        'avg_lead_time_days',
        'notes',
        'submitted_via',
        'captured_by_id',
        'portal_contact_name',
        'metadata_json',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_amount_monthly' => 'decimal:2',
            'total_amount_yearly' => 'decimal:2',
            'normalized_annual_amount' => 'decimal:2',
            'validity_until' => 'date',
            'recorded_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementRfqBid, $this> */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBid::class, 'bid_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'captured_by_id');
    }

    /** @return HasMany<ProcurementRfqBidVersionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementRfqBidVersionLine::class, 'version_id');
    }

    /** @return HasMany<ProcurementRfqBidAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProcurementRfqBidAttachment::class, 'version_id');
    }
}
