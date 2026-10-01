<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynField extends Model
{
    use HasUuids;

    protected $table = 'dyn_fields';

    protected $fillable = [
        'entity_id',
        'name',
        'label',
        'type',
        'is_required',
        'is_system_field',
        'system_column',
        'show_in_table',
        'is_filterable',
        'is_key',
        'calculate_totals',
        'options_json',
        'target_entity_id',
        'placeholder',
        'formula_definition',
        'column_span',
        'field_order',
        'form_group_id',
        'view_group_id',
        'conditional_rules_json',
        'is_virtual',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_system_field' => 'boolean',
            'show_in_table' => 'boolean',
            'is_filterable' => 'boolean',
            'is_key' => 'boolean',
            'calculate_totals' => 'boolean',
            'is_virtual' => 'boolean',
            'options_json' => 'array',
            'conditional_rules_json' => 'array',
            'column_span' => 'integer',
            'field_order' => 'integer',
        ];
    }

    /** @return BelongsTo<DynEntity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(DynEntity::class, 'entity_id');
    }

    /** @return BelongsTo<DynFieldGroup, $this> */
    public function formGroup(): BelongsTo
    {
        return $this->belongsTo(DynFieldGroup::class, 'form_group_id');
    }

    /** @return BelongsTo<DynEntity, $this> */
    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(DynEntity::class, 'target_entity_id');
    }
}
