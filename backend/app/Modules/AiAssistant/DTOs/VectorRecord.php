<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class VectorRecord
{
    /**
     * @param  list<float>  $embedding
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $vectorId,
        public string $chunkId,
        public string $sourceId,
        public array $embedding,
        public string $content,
        public array $metadata,
    ) {}
}
