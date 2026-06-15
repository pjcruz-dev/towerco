<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Jobs\ProcessStripeWebhookJob;
use App\Modules\Billing\Services\StripeBillingConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook as StripeWebhook;
use Symfony\Component\HttpFoundation\Response;

final class CentralStripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingConfig $config): JsonResponse
    {
        if (! $config->enabled()) {
            return response()->json(['message' => 'Stripe webhooks are disabled.'], Response::HTTP_NOT_FOUND);
        }

        $secret = $config->webhookSecret();
        if ($secret === null) {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set.');

            return response()->json(['message' => 'Webhook secret not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        try {
            $event = StripeWebhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_BAD_REQUEST);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $payloadArray */
        $payloadArray = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        ProcessStripeWebhookJob::dispatch($payloadArray);

        return response()->json(['received' => true]);
    }
}
