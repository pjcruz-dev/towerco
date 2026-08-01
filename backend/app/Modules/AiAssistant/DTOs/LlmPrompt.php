<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class LlmPrompt
{
    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @param  list<ToolResult>  $toolResults
     */
    public function __construct(
        public string $system,
        public string $user,
        public array $chunks = [],
        public ?string $moduleContext = null,
        public ?string $pagePath = null,
        public array $toolResults = [],
    ) {}
}
