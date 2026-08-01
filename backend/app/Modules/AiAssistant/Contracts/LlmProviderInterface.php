<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;

interface LlmProviderInterface
{
    public function complete(LlmPrompt $prompt): LlmCompletionResult;

    public function modelName(): string;
}
