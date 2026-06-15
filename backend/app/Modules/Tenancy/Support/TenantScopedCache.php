<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class TenantScopedCache
{
    /**
     * Remember a value with tenant-safe TTL. Uses array store in testing so PHPUnit does not require a cache table.
     */
    public static function remember(string $key, int $seconds, Closure $callback): mixed
    {
        if (self::shouldUseArrayStore()) {
            return Cache::store('array')->remember($key, $seconds, $callback);
        }

        try {
            return Cache::remember($key, $seconds, $callback);
        } catch (QueryException $exception) {
            if (! self::isMissingCacheTable($exception)) {
                throw $exception;
            }

            Log::warning('TenantScopedCache falling back to array store (cache table unavailable).', [
                'key' => $key,
            ]);

            return Cache::store('array')->remember($key, $seconds, $callback);
        }
    }

    private static function shouldUseArrayStore(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $store = (string) config('cache.default', 'database');
        if ($store === 'array') {
            return true;
        }

        if ($store !== 'database') {
            return false;
        }

        try {
            return ! Schema::connection((string) config('database.default'))->hasTable('cache');
        } catch (\Throwable) {
            return true;
        }
    }

    private static function isMissingCacheTable(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'no such table')
            && str_contains($message, 'cache');
    }
}
