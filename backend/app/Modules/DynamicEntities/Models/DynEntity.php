<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynEntity extends Model
{
    use HasUuids;

    protected $table = 'dyn_entities';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'module_pack',
        'storage_mode',
        'is_location_based',
        'source_linked_table',
        'related_tabs_json',
        'print_settings_json',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_location_based' => 'boolean',
            'is_active' => 'boolean',
            'related_tabs_json' => 'array',
            'print_settings_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<DynField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(DynField::class, 'entity_id')->orderBy('field_order');
    }

    /** @return HasMany<DynFieldGroup, $this> */
    public function fieldGroups(): HasMany
    {
        return $this->hasMany(DynFieldGroup::class, 'entity_id')->orderBy('sort_order');
    }

    /** @return HasMany<DynRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(DynRecord::class, 'entity_id');
    }
}
