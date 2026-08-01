<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class LlmCompletionResult
{
    /**
     * @param  list<string>  $suggestedFollowups
     */
    public function __construct(
        public string $answer,
        public string $modelName,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public int $latencyMs = 0,
        public bool $insufficientContext = false,
        public array $suggestedFollowups = [],
    ) {}
}
