<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use App\Modules\AiAssistant\Contracts\VectorStoreInterface;
use App\Modules\AiAssistant\DTOs\VectorRecord;
use App\Modules\AiAssistant\Models\AiKnowledgeChunk;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use App\Modules\AiAssistant\Support\GlobalHelpFrontMatterParser;
use App\Modules\AiAssistant\Support\KnowledgeTextChunker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class KnowledgeIngestionService
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly VectorStoreInterface $vectors,
        private readonly KnowledgeTextChunker $chunker,
        private readonly GlobalHelpFrontMatterParser $parser,
    ) {}

    /**
     * @return array{source_id: string, chunks: int, deleted: int}
     */
    public function ingestSource(AiKnowledgeSource $source): array
    {
        if ($source->status !== AssistantKnowledgeStatus::PUBLISHED) {
            throw new RuntimeException('Only published knowledge sources can be ingested.');
        }

        $body = $this->resolveSourceBody($source);
        $textChunks = $this->chunker->chunk($body);
        if ($textChunks === []) {
            throw new RuntimeException('Knowledge source has no ingestible content: '.$source->id);
        }

        $required = is_array($source->required_permissions) ? $source->required_permissions : [];
        /** @var list<string> $permissions */
        $permissions = array_values(array_map('strval', $required['permissions'] ?? []));
        /** @var list<string> $routes */
        $routes = array_values(array_map('strval', $required['related_routes'] ?? []));

        $tenantId = tenant()?->getTenantKey();
        $tenantId = is_string($tenantId) || is_int($tenantId) ? (string) $tenantId : null;

        return DB::transaction(function () use ($source, $textChunks, $permissions, $routes, $tenantId): array {
            $existingVectorIds = AiKnowledgeChunk::query()
                ->where('knowledge_source_id', $source->id)
                ->whereNotNull('vector_id')
                ->pluck('vector_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();

            if ($existingVectorIds !== []) {
                $this->vectors->delete($existingVectorIds);
            }

            AiKnowledgeChunk::query()->where('knowledge_source_id', $source->id)->delete();

            $embeddings = $this->embeddings->embedMany($textChunks);
            $records = [];
            $created = 0;

            foreach ($textChunks as $index => $content) {
                $chunkId = (string) Str::uuid();
                $vectorId = 'chunk:'.$chunkId;
                $checksum = hash('sha256', $content);
                $metadata = [
                    'tenant_id' => $tenantId,
                    'scope' => $source->scope,
                    'module_key' => $source->module_key,
                    'permissions' => $permissions,
                    'related_routes' => $routes,
                    'source_id' => (string) $source->id,
                    'chunk_id' => $chunkId,
                    'version' => (int) $source->version,
                    'status' => $source->status,
                    'slug' => $source->slug,
                    'title' => $source->title,
                    'chunk_index' => $index,
                ];

                AiKnowledgeChunk::query()->create([
                    'id' => $chunkId,
                    'knowledge_source_id' => $source->id,
                    'chunk_index' => $index,
                    'content' => $content,
                    'embedding_ref' => $vectorId,
                    'vector_id' => $vectorId,
                    'embedding' => $embeddings[$index],
                    'embedding_dimensions' => count($embeddings[$index]),
                    'embedding_model' => $this->embeddings->modelName(),
                    'metadata' => $metadata,
                    'checksum' => $checksum,
                    'indexed_at' => now(),
                ]);

                $records[] = new VectorRecord(
                    vectorId: $vectorId,
                    chunkId: $chunkId,
                    sourceId: (string) $source->id,
                    embedding: $embeddings[$index],
                    content: $content,
                    metadata: $metadata,
                );
                $created++;
            }

            $this->vectors->upsert($records);

            $source->forceFill(['last_indexed_at' => now()])->save();

            return [
                'source_id' => (string) $source->id,
                'chunks' => $created,
                'deleted' => count($existingVectorIds),
            ];
        });
    }

    /**
     * @return list<array{source_id: string, chunks: int, deleted: int}>
     */
    public function ingestPublishedSources(?string $sourceId = null): array
    {
        $query = AiKnowledgeSource::query()
            ->where('status', AssistantKnowledgeStatus::PUBLISHED)
            ->orderBy('slug');

        if ($sourceId !== null && $sourceId !== '') {
            $query->where('id', $sourceId);
        }

        $results = [];
        foreach ($query->get() as $source) {
            $results[] = $this->ingestSource($source);
        }

        return $results;
    }

    public function resolveSourceBody(AiKnowledgeSource $source): string
    {
        if (is_string($source->body) && trim($source->body) !== '') {
            return trim($source->title."\n\n".$source->body);
        }

        if ($source->scope === AssistantKnowledgeScope::GLOBAL
            && is_string($source->source_path)
            && $source->source_path !== ''
        ) {
            // Core global packs are stored relative to the AiAssistant module;
            // module help packs are stored relative to the Modules root.
            $candidates = [
                app_path('Modules/AiAssistant/'.$source->source_path),
                app_path('Modules/'.$source->source_path),
            ];

            foreach ($candidates as $absolute) {
                if (is_file($absolute)) {
                    $article = $this->parser->parseFile($absolute, $source->source_path);

                    return trim($article->title."\n\n".$article->body);
                }
            }
        }

        $firstChunk = AiKnowledgeChunk::query()
            ->where('knowledge_source_id', $source->id)
            ->orderBy('chunk_index')
            ->value('content');

        if (is_string($firstChunk) && trim($firstChunk) !== '') {
            // Re-ingest from concatenated existing chunk content when file is unavailable.
            $all = AiKnowledgeChunk::query()
                ->where('knowledge_source_id', $source->id)
                ->orderBy('chunk_index')
                ->pluck('content')
                ->implode("\n\n");

            return trim($all);
        }

        throw new RuntimeException('Unable to resolve knowledge source body for '.$source->id);
    }
}
