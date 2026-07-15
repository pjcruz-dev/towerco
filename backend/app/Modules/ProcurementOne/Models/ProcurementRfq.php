<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcurementRfq extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfqs';

    protected $fillable = [
        'document_no',
        'status',
        'title',
        'description',
        'pr_id',
        'project_id',
        'rollout_id',
        'site_id',
        'requestor_id',
        'bidding_opens_at',
        'bidding_closes_at',
        'awarded_vendor_id',
        'awarded_bid_id',
        'awarded_at',
        'awarded_by_id',
        'currency_code',
        'estimated_total',
        'award_notes',
        'notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'bidding_opens_at' => 'datetime',
            'bidding_closes_at' => 'datetime',
            'awarded_at' => 'datetime',
            'estimated_total' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementPr, $this> */
    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(ProcurementPr::class, 'pr_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }

    /** @return BelongsTo<ProcurementVendor, $this> */
    public function awardedVendor(): BelongsTo
    {
        return $this->belongsTo(ProcurementVendor::class, 'awarded_vendor_id');
    }

    /** @return BelongsTo<ProcurementRfqBid, $this> */
    public function awardedBid(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBid::class, 'awarded_bid_id');
    }

    /** @return HasMany<ProcurementRfqLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementRfqLine::class, 'rfq_id')->orderBy('line_order');
    }

    /** @return HasMany<ProcurementRfqVendor, $this> */
    public function invitedVendors(): HasMany
    {
        return $this->hasMany(ProcurementRfqVendor::class, 'rfq_id');
    }

    /** @return HasMany<ProcurementRfqBid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(ProcurementRfqBid::class, 'rfq_id')->orderByDesc('updated_at');
    }

    /** @return HasOne<ProcurementRfqPoLink, $this> */
    public function poLink(): HasOne
    {
        return $this->hasOne(ProcurementRfqPoLink::class, 'rfq_id');
    }
}
