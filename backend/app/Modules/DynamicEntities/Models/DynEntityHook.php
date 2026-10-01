<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynEntityHook extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'dyn_entity_hooks';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'entity_slug',
        'events',
        'definition_json',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'definition_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
