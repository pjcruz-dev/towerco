<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

/**
 * Planned tool call from the heuristic router (never LLM-chosen SQL / free-form code).
 */
final readonly class ToolCallRequest
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        public string $tool,
        public array $args = [],
    ) {}
}
