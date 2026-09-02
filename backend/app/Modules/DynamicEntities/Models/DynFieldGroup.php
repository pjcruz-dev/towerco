<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynFieldGroup extends Model
{
    use HasUuids;

    protected $table = 'dyn_field_groups';

    protected $fillable = [
        'entity_id',
        'name',
        'description',
        'icon',
        'applies_to_form',
        'applies_to_view',
        'start_collapsed',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_form' => 'boolean',
            'applies_to_view' => 'boolean',
            'start_collapsed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<DynEntity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(DynEntity::class, 'entity_id');
    }

    /** @return HasMany<DynField, $this> */
    public function formFields(): HasMany
    {
        return $this->hasMany(DynField::class, 'form_group_id')->orderBy('field_order');
    }
}
