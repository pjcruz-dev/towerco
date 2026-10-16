<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetNetworkAdapter extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_network_adapters';

    protected $fillable = [
        'asset_id', 'adapter_name', 'adapter_type', 'mac_address', 'ip_address', 'is_active', 'last_detected',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_detected' => 'datetime',
        ];
    }
}
