<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use RuntimeException;

/**
 * Raised when a configured AI provider (OpenAI, Bedrock, etc.) rejects requests due to quota/billing.
 */
final class AssistantProviderQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message,
    ) {
        parent::__construct($message);
    }
}
