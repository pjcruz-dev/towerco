<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class HostnameHistory extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'hostname_history';

    protected $fillable = [
        'asset_id', 'old_hostname', 'new_hostname', 'reason', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
