<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Support\TeamsWebhookUrl;
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
        $url = TeamsWebhookUrl::normalize($url);
        if (! TeamsWebhookUrl::isValid($url)) {
            return;
        }

        try {
            Http::timeout(max(1, $timeoutSeconds))->asJson()->post($url, $payload)->throw();
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
        $url = TeamsWebhookUrl::normalize($url);
        Http::timeout(max(1, $timeoutSeconds))->asJson()->post($url, $payload)->throw();
    }
}
