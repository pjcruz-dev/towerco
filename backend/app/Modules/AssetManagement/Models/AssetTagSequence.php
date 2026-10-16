<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetTagSequence extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_tag_sequences';

    protected $fillable = ['prefix', 'next_number'];
}
