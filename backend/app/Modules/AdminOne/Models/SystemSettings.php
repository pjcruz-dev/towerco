<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemSettings extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $connection = 'tenant';

    protected $table = 'system_settings';

    protected $fillable = [
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }
}
