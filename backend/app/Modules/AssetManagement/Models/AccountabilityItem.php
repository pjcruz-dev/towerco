<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AccountabilityItem extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'accountability_items';

    protected $fillable = ['accountability_id', 'asset_id', 'label', 'sort_order'];
}
