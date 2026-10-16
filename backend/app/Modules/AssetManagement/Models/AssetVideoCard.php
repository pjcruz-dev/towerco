<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetVideoCard extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_video_cards';

    protected $fillable = ['asset_id', 'name', 'driver_version', 'last_detected'];

    protected function casts(): array
    {
        return ['last_detected' => 'datetime'];
    }
}
