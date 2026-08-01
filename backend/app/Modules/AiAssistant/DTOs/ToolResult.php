<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data  Summarized safe payload for the model
     * @param  list<string>  $relatedRoutes
     */
    public function __construct(
        public string $tool,
        public bool $ok,
        public array $data = [],
        public ?string $error = null,
        public ?string $summary = null,
        public ?string $moduleKey = null,
        public array $relatedRoutes = [],
        public int $rowCount = 0,
    ) {}

    /**
     * Citation row distinct from document RAG citations.
     *
     * @return array<string, mixed>
     */
    public function toCitationArray(): array
    {
        return [
            'type' => 'live_data',
            'chunk_id' => 'tool:'.$this->tool,
            'source_id' => 'tool:'.$this->tool,
            'title' => 'Live system data: '.str_replace('_', ' ', $this->tool),
            'slug' => null,
            'module' => $this->moduleKey,
            'scope' => 'live',
            'version' => 0,
            'score' => 1.0,
            'related_routes' => $this->relatedRoutes,
            'excerpt' => $this->summary ?? ($this->ok ? 'Tool returned live data.' : ($this->error ?? 'Tool failed.')),
            'tool' => $this->tool,
            'ok' => $this->ok,
            'row_count' => $this->rowCount,
        ];
    }

    /**
     * Compact JSON-serializable payload for prompts / audit.
     *
     * @return array<string, mixed>
     */
    public function toPromptArray(): array
    {
        return [
            'tool' => $this->tool,
            'ok' => $this->ok,
            'error' => $this->error,
            'summary' => $this->summary,
            'row_count' => $this->rowCount,
            'related_routes' => $this->relatedRoutes,
            'data' => $this->data,
        ];
    }
}
