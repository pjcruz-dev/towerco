<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetRepairReplacement extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_repair_replacements';

    protected $fillable = [
        'repair_id', 'original_asset_id', 'replacement_asset_id', 'replacement_type',
        'status', 'issued_at', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
