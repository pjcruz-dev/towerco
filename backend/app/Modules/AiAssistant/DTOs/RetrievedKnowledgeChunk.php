<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class RetrievedKnowledgeChunk
{
    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $relatedRoutes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $chunkId,
        public string $sourceId,
        public string $vectorId,
        public string $content,
        public float $score,
        public string $scope,
        public ?string $moduleKey,
        public string $title,
        public ?string $slug,
        public int $version,
        public array $permissions,
        public array $relatedRoutes,
        public array $metadata = [],
        public ?string $sourceBody = null,
    ) {}

    /**
     * Full source document text when available (falls back to the chunk fragment).
     */
    public function body(): string
    {
        return $this->sourceBody !== null && trim($this->sourceBody) !== ''
            ? $this->sourceBody
            : $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toCitationArray(): array
    {
        return [
            'type' => 'document',
            'chunk_id' => $this->chunkId,
            'source_id' => $this->sourceId,
            'title' => $this->title,
            'slug' => $this->slug,
            'module' => $this->moduleKey,
            'scope' => $this->scope,
            'version' => $this->version,
            'score' => round($this->score, 4),
            'related_routes' => $this->relatedRoutes,
            'excerpt' => mb_substr($this->content, 0, 240),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->toCitationArray(),
            'content' => $this->content,
            'permissions' => $this->permissions,
            'metadata' => $this->metadata,
        ];
    }
}
