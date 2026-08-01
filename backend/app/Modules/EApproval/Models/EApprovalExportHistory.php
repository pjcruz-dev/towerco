<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalExportHistory extends Model
{
    use HasUuids;

    protected $table = 'e_approval_export_histories';

    protected $fillable = [
        'user_id',
        'report_definition_id',
        'name',
        'filters_json',
        'columns_json',
        'layout',
        'format',
        'grid_field_id',
        'matched_rows',
        'exported_rows',
        'truncated',
        'status',
        'triggered_by',
        'filename',
        'file_path',
        'disk',
        'content_type',
        'byte_size',
        'expires_at',
        'error_message',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'columns_json' => 'array',
            'truncated' => 'boolean',
            'matched_rows' => 'integer',
            'exported_rows' => 'integer',
            'byte_size' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /** @return BelongsTo<EApprovalReportDefinition, $this> */
    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(EApprovalReportDefinition::class, 'report_definition_id');
    }
}
