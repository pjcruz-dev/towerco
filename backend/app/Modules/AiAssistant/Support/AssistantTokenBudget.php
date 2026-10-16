<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Same per-minute ask budget the assistant header shows. A mapping call spends one token.
 */
final class AssistantTokenBudget
{
    public function spend(TenantUser $user): bool
    {
        $driver = strtolower((string) config('ai_assistant.llm_provider', ''));
        if (! in_array($driver, ['cursor', 'cursor_ai', 'gemini', 'google', 'google_ai', 'ai_studio', 'openai', 'chatgpt', 'bedrock'], true)) {
            return false;
        }

        $limit = max(5, (int) config('ai_assistant.rate_limit_per_minute', 20));
        $key = md5('assistant'.(string) $user->getAuthIdentifier().'|assistant');
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
