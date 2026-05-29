<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminSettings extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $connection = 'tenant';

    protected $table = 'admin_settings';

    protected $fillable = [
        'kpi_config',
        'sla_config',
        'workflow_templates',
    ];

    protected function casts(): array
    {
        return [
            'kpi_config' => 'array',
            'sla_config' => 'array',
            'workflow_templates' => 'array',
        ];
    }
}
