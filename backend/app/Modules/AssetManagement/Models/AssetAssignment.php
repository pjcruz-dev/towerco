<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_assignments';

    protected $fillable = [
        'asset_id', 'employee_id', 'assigned_at', 'returned_at', 'status',
        'assigned_by', 'returned_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'employee_id');
    }
}
