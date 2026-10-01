<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynRecord extends Model
{
    use HasUuids;

    protected $table = 'dyn_records';

    protected $fillable = [
        'entity_id',
        'status',
        'title',
        'values_json',
        'parent_record_id',
        'location_id',
        'assigned_user_id',
        'source_external_id',
        'is_deleted',
        'deleted_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'values_json' => 'array',
            'is_deleted' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DynEntity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(DynEntity::class, 'entity_id');
    }

    /** @return BelongsTo<DynRecord, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DynRecord::class, 'parent_record_id');
    }

    /** @return HasMany<DynRecordIndex, $this> */
    public function indexes(): HasMany
    {
        return $this->hasMany(DynRecordIndex::class, 'record_id');
    }
}
