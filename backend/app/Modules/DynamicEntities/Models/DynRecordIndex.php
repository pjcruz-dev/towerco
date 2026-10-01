<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynRecordIndex extends Model
{
    use HasUuids;

    protected $table = 'dyn_record_indexes';

    protected $fillable = [
        'record_id',
        'entity_id',
        'field_name',
        'value_string',
        'value_number',
        'value_date',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'date',
        ];
    }

    /** @return BelongsTo<DynRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(DynRecord::class, 'record_id');
    }
}
