<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiKnowledgeChunk extends Model
{
    use HasUuids;

    protected $table = 'ai_knowledge_chunks';

    protected $fillable = [
        'knowledge_source_id',
        'chunk_index',
        'content',
        'embedding_ref',
        'vector_id',
        'embedding',
        'embedding_dimensions',
        'embedding_model',
        'metadata',
        'checksum',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'metadata' => 'array',
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
            'indexed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiKnowledgeSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeSource::class, 'knowledge_source_id');
    }
}
