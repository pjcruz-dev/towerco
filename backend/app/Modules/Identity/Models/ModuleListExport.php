<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleListExport extends BaseModel
{
    protected $table = 'module_list_exports';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const SYNC_MAX_ROWS = 5000;

    public const ASYNC_MAX_ROWS = 50_000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'module',
        'filename',
        'format',
        'status',
        'disk',
        'file_path',
        'matched_rows',
        'exported_rows',
        'truncated',
        'filters_json',
        'error_message',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'truncated' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }
}
