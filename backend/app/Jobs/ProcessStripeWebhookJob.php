<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Billing\Services\StripeWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stripe\Event as StripeEvent;

final class ProcessStripeWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
        $this->onQueue(config('toweros.queues.webhooks', 'toweros-webhooks'));
    }

    public function handle(StripeWebhookProcessor $processor): void
    {
        $event = StripeEvent::constructFrom($this->payload);
        $processor->process($event);
    }
}
