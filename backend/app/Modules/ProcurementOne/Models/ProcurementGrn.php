<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementGrn extends Model
{
    use HasUuids;

    protected $table = 'procurement_grns';

    protected $fillable = [
        'document_no',
        'status',
        'po_id',
        'received_by_id',
        'project_id',
        'rollout_id',
        'site_id',
        'inventory_location_id',
        'gps_latitude',
        'gps_longitude',
        'gps_accuracy_meters',
        'received_at',
        'posted_at',
        'notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'gps_latitude' => 'decimal:7',
            'gps_longitude' => 'decimal:7',
            'gps_accuracy_meters' => 'decimal:2',
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementPo, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPo::class, 'po_id');
    }

    /** @return BelongsTo<ProcurementInventoryLocation, $this> */
    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(ProcurementInventoryLocation::class, 'inventory_location_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'received_by_id');
    }

    /** @return HasMany<ProcurementGrnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementGrnLine::class, 'grn_id')->orderBy('line_order');
    }

    /** @return HasMany<ProcurementGrnAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProcurementGrnAttachment::class, 'grn_id')->orderByDesc('created_at');
    }
}
