<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Remaining asks for the named "assistant" throttle (same key shape as ThrottleRequests).
 */
final class AssistantRateLimitReporter
{
    /**
     * @return array{
     *   limit: int,
     *   remaining: int,
     *   resets_in_seconds: int,
     *   daily: array{limit: int, remaining: int, resets_in_seconds: int}|null
     * }
     */
    public static function forRequest(Request $request): array
    {
        $limit = max(5, (int) config('ai_assistant.rate_limit_per_minute', 20));
        $by = ($request->user()?->getAuthIdentifier() ?: $request->ip() ?: 'guest').'|assistant';
        // Must match Illuminate\Routing\Middleware\ThrottleRequests named-limiter key.
        $key = md5('assistant'.$by);

        $remaining = max(0, RateLimiter::remaining($key, $limit));
        $resetsIn = RateLimiter::attempts($key) > 0
            ? max(0, RateLimiter::availableIn($key))
            : 0;

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'resets_in_seconds' => $resetsIn,
            'daily' => AssistantDailyUsageReporter::forRequest($request),
        ];
    }
}
