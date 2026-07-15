<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementInventoryLocation extends Model
{
    use HasUuids;

    protected $table = 'procurement_inventory_locations';

    protected $fillable = [
        'code',
        'name',
        'location_kind',
        'site_id',
        'is_default_receipt',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default_receipt' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ProcurementInventoryStockBalance, $this> */
    public function balances(): HasMany
    {
        return $this->hasMany(ProcurementInventoryStockBalance::class, 'location_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
