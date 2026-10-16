<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetEvent extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_events';

    protected $fillable = [
        'asset_id', 'event_type', 'from_place', 'to_place', 'payload_json', 'actor_user_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
