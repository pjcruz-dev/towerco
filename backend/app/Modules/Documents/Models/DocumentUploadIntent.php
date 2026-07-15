<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentUploadIntent extends Model
{
    use HasUuids;

    protected $table = 'document_upload_intents';

    protected $fillable = [
        'upload_token',
        'site_id',
        'site_node_id',
        'document_id',
        'stored_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
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
}
