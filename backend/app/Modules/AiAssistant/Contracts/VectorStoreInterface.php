<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\VectorRecord;

interface VectorStoreInterface
{
    /**
     * @param  list<VectorRecord>  $records
     */
    public function upsert(array $records): void;

    /**
     * @param  list<string>  $vectorIds
     */
    public function delete(array $vectorIds): void;

    /**
     * @param  list<float>  $queryEmbedding
     * @param  array{
     *   tenant_id?: string|null,
     *   scopes?: list<string>,
     *   module_keys?: list<string>|null,
     *   status?: string,
     *   top_k?: int
     * }  $filters
     * @return list<RetrievedKnowledgeChunk>
     */
    public function search(array $queryEmbedding, array $filters = []): array;
}
