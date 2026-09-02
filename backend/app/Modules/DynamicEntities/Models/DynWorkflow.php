<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynWorkflow extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'dyn_workflows';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'entity_slug',
        'trigger_mode',
        'status_field',
        'status_matches',
        'role_ids',
        'definition_json',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status_matches' => 'array',
            'role_ids' => 'array',
            'definition_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
