<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'site_id',
        'site_node_id',
        'title',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'status',
        'version',
        'expires_at',
        'sort_order',
        'uploaded_by_id',
        'last_touched_by_id',
        'last_touched_at',
        'e_approval_submission_id',
        'approval_status',
        'source_rollout_file_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version' => 'integer',
            'sort_order' => 'integer',
            'expires_at' => 'datetime',
            'last_touched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /** @return BelongsTo<DocumentSiteNode, $this> */
    public function siteNode(): BelongsTo
    {
        return $this->belongsTo(DocumentSiteNode::class, 'site_node_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function lastTouchedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'last_touched_by_id');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderByDesc('version');
    }

    /** @return HasMany<DocumentActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class, 'document_id')->orderByDesc('created_at');
    }
}
