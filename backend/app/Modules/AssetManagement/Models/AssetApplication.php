<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetApplication extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_applications';

    protected $fillable = [
        'asset_id', 'application_name', 'version', 'publisher', 'installed_date', 'last_detected',
    ];

    protected function casts(): array
    {
        return [
            'installed_date' => 'date',
            'last_detected' => 'datetime',
        ];
    }
}
