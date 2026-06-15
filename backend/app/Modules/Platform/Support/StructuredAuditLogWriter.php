<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Facades\Log;

final class StructuredAuditLogWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(string $stream, string $event, array $payload = []): void
    {
        if (! (bool) config('toweros.logging.audit_structured_enabled', true)) {
            return;
        }

        $channel = (string) config('toweros.logging.audit_channel', 'audit');

        Log::channel($channel)->info($event, array_merge([
            'stream' => $stream,
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'correlation_id' => request()->header('X-Correlation-Id') ?? request()->header('X-Request-Id'),
            'ip_address' => request()->ip(),
        ], $payload));
    }
}
