<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingItem extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'offboarding_items';

    protected $fillable = [
        'offboarding_id', 'asset_id', 'result', 'condition', 'physical_damage', 'accessories',
        'battery_condition', 'hardware_condition', 'notes', 'inspected_by', 'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'physical_damage' => 'boolean',
            'inspected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
