<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlledDocumentReportDefinition extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'controlled_document_report_definitions';

    protected $fillable = [
        'user_id',
        'name',
        'filters_json',
        'schedule_json',
        'last_run_at',
        'last_export_path',
        'last_export_filename',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'schedule_json' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }
}
