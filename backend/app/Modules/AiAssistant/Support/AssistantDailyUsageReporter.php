<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Optional per-user daily ask cap (independent of the per-minute throttle).
 */
final class AssistantDailyUsageReporter
{
    /**
     * @return array{limit: int, remaining: int, resets_in_seconds: int}|null
     */
    public static function forRequest(Request $request): ?array
    {
        $limit = self::configuredLimit();
        if ($limit === null) {
            return null;
        }

        $userId = $request->user()?->getAuthIdentifier();
        if ($userId === null || $userId === '') {
            return null;
        }

        $used = self::usedCount((string) $userId);

        return [
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'resets_in_seconds' => max(0, now()->endOfDay()->diffInSeconds(now())),
        ];
    }

    public static function wouldExceed(Request $request): bool
    {
        $limit = self::configuredLimit();
        if ($limit === null) {
            return false;
        }

        $userId = $request->user()?->getAuthIdentifier();
        if ($userId === null || $userId === '') {
            return false;
        }

        return self::usedCount((string) $userId) >= $limit;
    }

    public static function recordAsk(Request $request): void
    {
        $limit = self::configuredLimit();
        if ($limit === null) {
            return;
        }

        $userId = $request->user()?->getAuthIdentifier();
        if ($userId === null || $userId === '') {
            return;
        }

        $key = self::cacheKey((string) $userId);
        if (! Cache::has($key)) {
            Cache::put($key, 1, now()->endOfDay());

            return;
        }

        Cache::increment($key);
    }

    private static function configuredLimit(): ?int
    {
        $limit = (int) config('ai_assistant.daily_ask_limit', 0);

        return $limit > 0 ? $limit : null;
    }

    private static function usedCount(string $userId): int
    {
        return max(0, (int) Cache::get(self::cacheKey($userId), 0));
    }

    private static function cacheKey(string $userId): string
    {
        return 'assistant-daily:'.$userId.':'.now()->format('Y-m-d');
    }
}
