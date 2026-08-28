<?php

declare(strict_types=1);

namespace App\Modules\Help\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpGuide extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const ROLE_REQUESTOR = 'requestor';

    public const ROLE_APPROVER = 'approver';

    public const ROLE_ALL = 'all';

    protected $table = 'help_guides';

    protected $fillable = [
        'module_key',
        'slug',
        'role',
        'title',
        'body',
        'status',
        'sort_order',
        'content_checksum',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'updated_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
