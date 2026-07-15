<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSiteNode extends Model
{
    use HasUuids;

    protected $table = 'document_site_nodes';

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'node_key',
        'label',
        'node_type',
        'sort_order',
        'lessor_name',
        'lessor_contact',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<DocumentSiteWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(DocumentSiteWorkspace::class, 'workspace_id');
    }

    /** @return BelongsTo<DocumentSiteNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<DocumentSiteNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'site_node_id');
    }
}
