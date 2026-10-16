<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetFieldIndex extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_field_indexes';

    protected $fillable = ['asset_id', 'field_name', 'value_string', 'value_number', 'value_date'];

    protected function casts(): array
    {
        return ['value_date' => 'date'];
    }
}
