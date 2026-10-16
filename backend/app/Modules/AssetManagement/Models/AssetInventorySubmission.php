<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetInventorySubmission extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_inventory_submissions';

    protected $fillable = [
        'client_key', 'asset_id', 'windows_identity', 'matched_user_id', 'submitted_by', 'payload_json',
    ];

    protected function casts(): array
    {
        return ['payload_json' => 'array'];
    }
}
