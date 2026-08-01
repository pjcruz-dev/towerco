<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RolloutGeographyLookup extends Model
{
    use HasUuids;

    public const KIND_REGION = 'region';

    public const KIND_TERRITORY = 'territory';

    protected $connection = 'tenant';

    protected $table = 'rollout_geography_lookups';

    protected $fillable = [
        'kind',
        'code',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
