<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\VectorStoreInterface;
use App\Modules\AiAssistant\DTOs\VectorRecord;
use RuntimeException;

/**
 * Amazon OpenSearch Serverless adapter placeholder.
 * Wire AWS SigV4 HTTP client / opensearch-php when AI_ASSISTANT_VECTOR_STORE=opensearch.
 */
final class OpenSearchVectorStore implements VectorStoreInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $index,
    ) {}

    public function upsert(array $records): void
    {
        $this->assertConfigured();
        throw new RuntimeException(
            'OpenSearch vector upsert is not fully wired in this environment. Use AI_ASSISTANT_VECTOR_STORE=database for local/dev, or complete the OpenSearch client integration for production.',
        );
    }

    public function delete(array $vectorIds): void
    {
        $this->assertConfigured();
        throw new RuntimeException(
            'OpenSearch vector delete is not fully wired in this environment. Use AI_ASSISTANT_VECTOR_STORE=database for local/dev.',
        );
    }

    public function search(array $queryEmbedding, array $filters = []): array
    {
        $this->assertConfigured();
        throw new RuntimeException(
            'OpenSearch vector search is not fully wired in this environment. Use AI_ASSISTANT_VECTOR_STORE=database for local/dev.',
        );
    }

    private function assertConfigured(): void
    {
        if (trim($this->endpoint) === '' || trim($this->index) === '') {
            throw new RuntimeException(
                'OpenSearch is not configured. Set AI_ASSISTANT_OPENSEARCH_ENDPOINT and AI_ASSISTANT_OPENSEARCH_INDEX, or use AI_ASSISTANT_VECTOR_STORE=database.',
            );
        }
    }
}
