<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSetting extends Model
{
    protected $connection = 'tenant';

    protected $table = 'asset_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value_json'];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }
}
