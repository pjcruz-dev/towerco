<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiKnowledgeSource extends Model
{
    use HasUuids;

    protected $table = 'ai_knowledge_sources';

    protected $fillable = [
        'slug',
        'scope',
        'module_key',
        'title',
        'source_type',
        'source_path',
        'source_url',
        'body',
        'audience',
        'required_permissions',
        'status',
        'version',
        'content_checksum',
        'published_at',
        'last_indexed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'required_permissions' => 'array',
            'version' => 'integer',
            'published_at' => 'datetime',
            'last_indexed_at' => 'datetime',
        ];
    }

    /** @return HasMany<AiKnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class, 'knowledge_source_id')->orderBy('chunk_index');
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
}
