<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantActivityLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'tenant_activity_logs';

    protected $connection = 'tenant';

    protected $fillable = [
        'module',
        'action',
        'summary',
        'entity_type',
        'entity_id',
        'entity_label',
        'actor_user_id',
        'ip_address',
        'metadata_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'actor_user_id');
    }
}
