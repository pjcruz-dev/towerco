<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TeamsWebhookHttpPoster
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public static function postOrLog(
        string $url,
        array $payload,
        int $timeoutSeconds,
        string $logKey,
        array $context = [],
    ): void {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        try {
            Http::timeout(max(1, $timeoutSeconds))->post($url, $payload)->throw();
        } catch (\Throwable $e) {
            Log::warning($logKey, array_merge($context, [
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function postOrThrow(string $url, array $payload, int $timeoutSeconds): void
    {
        Http::timeout(max(1, $timeoutSeconds))->post($url, $payload)->throw();
    }
}
