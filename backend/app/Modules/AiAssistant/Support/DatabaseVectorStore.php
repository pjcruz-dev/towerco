<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\VectorStoreInterface;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\VectorRecord;
use App\Modules\AiAssistant\Models\AiKnowledgeChunk;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use RuntimeException;

/**
 * Local/dev vector store: embeddings live on ai_knowledge_chunks; search uses cosine similarity.
 */
final class DatabaseVectorStore implements VectorStoreInterface
{
    public function upsert(array $records): void
    {
        foreach ($records as $record) {
            if (! $record instanceof VectorRecord) {
                throw new RuntimeException('DatabaseVectorStore expects VectorRecord instances.');
            }

            $chunk = AiKnowledgeChunk::query()->find($record->chunkId);
            if ($chunk === null) {
                continue;
            }

            $chunk->forceFill([
                'embedding' => $record->embedding,
                'embedding_dimensions' => count($record->embedding),
                'vector_id' => $record->vectorId,
                'embedding_ref' => $record->vectorId,
                'indexed_at' => now(),
            ])->save();
        }
    }

    public function delete(array $vectorIds): void
    {
        if ($vectorIds === []) {
            return;
        }

        AiKnowledgeChunk::query()
            ->whereIn('vector_id', $vectorIds)
            ->update([
                'embedding' => null,
                'embedding_dimensions' => null,
                'vector_id' => null,
                'embedding_ref' => null,
                'indexed_at' => null,
            ]);
    }

    public function search(array $queryEmbedding, array $filters = []): array
    {
        $topK = max(1, (int) ($filters['top_k'] ?? 5));
        $status = (string) ($filters['status'] ?? AssistantKnowledgeStatus::PUBLISHED);
        /** @var list<string> $scopes */
        $scopes = array_values(array_filter(
            array_map('strval', $filters['scopes'] ?? ['global', 'tenant']),
        ));
        /** @var list<string>|null $moduleKeys */
        $moduleKeys = $filters['module_keys'] ?? null;

        $query = AiKnowledgeChunk::query()
            ->with('source')
            ->whereNotNull('embedding')
            ->whereHas('source', static function ($q) use ($status, $scopes, $moduleKeys): void {
                $q->where('status', $status)
                    ->whereIn('scope', $scopes);

                if (is_array($moduleKeys)) {
                    $q->where(static function ($inner) use ($moduleKeys): void {
                        $inner->whereIn('module_key', $moduleKeys)
                            ->orWhereNull('module_key');
                    });
                }
            });

        $scored = [];
        foreach ($query->cursor() as $chunk) {
            /** @var AiKnowledgeChunk $chunk */
            $source = $chunk->source;
            if (! $source instanceof AiKnowledgeSource) {
                continue;
            }

            $embedding = $chunk->embedding;
            if (! is_array($embedding) || $embedding === []) {
                continue;
            }

            /** @var list<float> $embeddingVector */
            $embeddingVector = array_map(static fn (mixed $v): float => (float) $v, array_values($embedding));
            $score = VectorMath::cosineSimilarity($queryEmbedding, $embeddingVector);

            $meta = is_array($chunk->metadata) ? $chunk->metadata : [];
            $required = is_array($source->required_permissions) ? $source->required_permissions : [];
            /** @var list<string> $permissions */
            $permissions = array_values(array_map(
                'strval',
                $required['permissions'] ?? ($meta['permissions'] ?? []),
            ));
            /** @var list<string> $routes */
            $routes = array_values(array_map(
                'strval',
                $required['related_routes'] ?? ($meta['related_routes'] ?? []),
            ));

            $scored[] = new RetrievedKnowledgeChunk(
                chunkId: (string) $chunk->id,
                sourceId: (string) $source->id,
                vectorId: (string) ($chunk->vector_id ?: $chunk->id),
                content: (string) $chunk->content,
                score: $score,
                scope: (string) $source->scope,
                moduleKey: $source->module_key !== null ? (string) $source->module_key : null,
                title: (string) $source->title,
                slug: $source->slug !== null ? (string) $source->slug : null,
                version: (int) $source->version,
                permissions: $permissions,
                relatedRoutes: $routes,
                metadata: $meta,
            );
        }

        usort(
            $scored,
            static fn (RetrievedKnowledgeChunk $a, RetrievedKnowledgeChunk $b): int => $b->score <=> $a->score,
        );

        $top = array_slice($scored, 0, $topK);

        // Attach the full source document to each returned chunk so answer shaping
        // parses complete markdown sections instead of an 800-char fragment.
        $bodyCache = [];

        return array_map(function (RetrievedKnowledgeChunk $chunk) use (&$bodyCache): RetrievedKnowledgeChunk {
            if (! array_key_exists($chunk->sourceId, $bodyCache)) {
                $bodyCache[$chunk->sourceId] = $this->resolveFullBody($chunk->sourceId);
            }

            return new RetrievedKnowledgeChunk(
                chunkId: $chunk->chunkId,
                sourceId: $chunk->sourceId,
                vectorId: $chunk->vectorId,
                content: $chunk->content,
                score: $chunk->score,
                scope: $chunk->scope,
                moduleKey: $chunk->moduleKey,
                title: $chunk->title,
                slug: $chunk->slug,
                version: $chunk->version,
                permissions: $chunk->permissions,
                relatedRoutes: $chunk->relatedRoutes,
                metadata: $chunk->metadata,
                sourceBody: $bodyCache[$chunk->sourceId],
            );
        }, $top);
    }

    private function resolveFullBody(string $sourceId): ?string
    {
        $source = AiKnowledgeSource::query()->find($sourceId);
        if ($source instanceof AiKnowledgeSource) {
            // Prefer the stored source body — never implode overlapping chunks
            // (overlap would duplicate intro/title in the reconstructed text).
            if (is_string($source->body) && trim($source->body) !== '') {
                return trim($source->title."\n\n".$source->body);
            }

            if (is_string($source->source_path) && $source->source_path !== '') {
                $candidates = [
                    app_path('Modules/AiAssistant/'.$source->source_path),
                    app_path('Modules/'.$source->source_path),
                ];
                foreach ($candidates as $absolute) {
                    if (is_file($absolute)) {
                        $raw = file_get_contents($absolute);
                        if ($raw === false) {
                            break;
                        }
                        $normalized = str_replace("\r\n", "\n", $raw);
                        $end = strpos($normalized, "\n---\n", 4);
                        if ($end !== false) {
                            $markdownBody = trim(substr($normalized, $end + 5));
                            if ($markdownBody !== '') {
                                return trim($source->title."\n\n".$markdownBody);
                            }
                        }
                    }
                }
            }
        }

        // Last resort: first chunk only (avoid overlapping-chunk duplication).
        $first = AiKnowledgeChunk::query()
            ->where('knowledge_source_id', $sourceId)
            ->orderBy('chunk_index')
            ->value('content');

        $first = is_string($first) ? trim($first) : '';

        return $first !== '' ? $first : null;
    }
}
