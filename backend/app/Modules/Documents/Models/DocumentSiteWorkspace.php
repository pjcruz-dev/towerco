<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSiteWorkspace extends Model
{
    use HasUuids;

    protected $table = 'document_site_workspaces';

    protected $fillable = [
        'site_id',
        'rollout_program_id',
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /** @return HasMany<DocumentSiteNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(DocumentSiteNode::class, 'workspace_id')->orderBy('sort_order');
    }
}
