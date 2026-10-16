<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetDisk extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_disks';

    protected $fillable = ['asset_id', 'model', 'serial_number', 'size_label', 'interface', 'last_detected'];

    protected function casts(): array
    {
        return ['last_detected' => 'datetime'];
    }
}
