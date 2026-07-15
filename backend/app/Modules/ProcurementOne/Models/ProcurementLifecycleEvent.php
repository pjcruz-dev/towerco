<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementLifecycleEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'procurement_lifecycle_events';

    protected $fillable = [
        'document_type',
        'document_id',
        'document_no',
        'action',
        'reason',
        'actor_user_id',
        'metadata_json',
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
