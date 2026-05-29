<?php

declare(strict_types=1);

namespace App\Modules\TowerOne\Models;

use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tower extends Model
{
    use HasUuids;

    protected $table = 'towers';

    protected $fillable = [
        'site_id',
        'tower_type',
        'height_m',
        'capacity_kg',
        'max_tenants',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'height_m' => 'decimal:2',
            'capacity_kg' => 'decimal:2',
            'max_tenants' => 'integer',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
