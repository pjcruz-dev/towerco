<?php

declare(strict_types=1);

namespace App\Core\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for application services: transactions, logging hooks, and event dispatch.
 */
abstract class AbstractDomainService
{
    public function __construct(
        protected readonly Dispatcher $events,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel(config('toweros.logging.application_channel', 'stack'))
            ->info($message, $this->baseContext($context));
    }

    protected function logWarning(string $message, array $context = []): void
    {
        Log::channel(config('toweros.logging.application_channel', 'stack'))
            ->warning($message, $this->baseContext($context));
    }

    protected function logError(Throwable $e, string $message, array $context = []): void
    {
        Log::channel(config('toweros.logging.application_channel', 'stack'))
            ->error($message, $this->baseContext(array_merge($context, [
                'exception' => $e::class,
                'exception_message' => $e->getMessage(),
            ])));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function baseContext(array $context): array
    {
        $request = request();

        return array_merge([
            'correlation_id' => $request?->attributes->get('correlation_id'),
            'tenant_id' => tenant()?->getTenantKey(),
        ], $context);
    }
}
