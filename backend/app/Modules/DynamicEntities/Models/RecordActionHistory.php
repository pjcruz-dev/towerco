<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordActionHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'record_action_histories';

    protected $fillable = [
        'module',
        'entity_slug',
        'entity_label',
        'kind',
        'record_count',
        'record_id',
        'field_label',
        'summary',
        'actor_user_id',
        'actor_name',
        'before_json',
        'after_json',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'record_count' => 'integer',
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'actor_user_id');
    }
}
