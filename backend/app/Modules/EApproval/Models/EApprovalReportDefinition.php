<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalReportDefinition extends Model
{
    use HasUuids;

    protected $table = 'e_approval_report_definitions';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'filters_json',
        'columns_json',
        'layout',
        'format',
        'grid_field_id',
        'schedule_json',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'columns_json' => 'array',
            'schedule_json' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /** @return HasMany<EApprovalExportHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(EApprovalExportHistory::class, 'report_definition_id');
    }
}
