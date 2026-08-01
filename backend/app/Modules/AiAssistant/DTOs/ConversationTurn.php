<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

/**
 * A sanitized prior turn for prompt grounding / follow-up resolution.
 */
final readonly class ConversationTurn
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}
}
