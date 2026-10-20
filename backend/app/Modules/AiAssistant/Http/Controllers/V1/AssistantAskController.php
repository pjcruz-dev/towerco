<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\DTOs\AskAssistantInput;
use App\Modules\AiAssistant\Http\Requests\AskAssistantRequest;
use App\Modules\AiAssistant\Services\AssistantService;
use App\Modules\AiAssistant\Support\AssistantDailyUsageReporter;
use App\Modules\AiAssistant\Support\AssistantRateLimitReporter;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssistantAskController extends AbstractApiController
{
    public function __invoke(AskAssistantRequest $request, AssistantService $service): JsonResponse|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));

        if (AssistantDailyUsageReporter::wouldExceed($request)) {
            return response()->json([
                'message' => __('Daily assistant ask limit reached. Try again tomorrow.'),
                'data' => [
                    'rate_limit' => AssistantRateLimitReporter::forRequest($request),
                ],
            ], 429);
        }

        $input = AskAssistantInput::fromValidated($request->validatedPayload());
        $wantsStream = str_contains(strtolower((string) $request->header('Accept')), 'text/event-stream');

        if (! $wantsStream) {
            $result = $service->ask($user, $input);
            AssistantDailyUsageReporter::recordAsk($request);
            $payload = $result->toArray();
            $payload['rate_limit'] = AssistantRateLimitReporter::forRequest($request);

            return $this->ok($payload);
        }

        return response()->stream(function () use ($request, $service, $user, $input): void {
            $this->beginEventStream();

            try {
                $result = $service->ask($user, $input, function (array $step): void {
                    $this->writeEvent('step', $step);
                }, function (array $delta): void {
                    $this->writeEvent('delta', $delta);
                });
                AssistantDailyUsageReporter::recordAsk($request);
                $payload = $result->toArray();
                $payload['rate_limit'] = AssistantRateLimitReporter::forRequest($request);
                $this->writeEvent('result', $payload);
            } catch (Throwable $e) {
                report($e);
                $this->writeEvent('error', [
                    'message' => $this->safeStreamMessage($e),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function beginEventStream(): void
    {
        @set_time_limit(0);
        @ini_set('default_socket_timeout', '360');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        echo ": connected\n\n";
        flush();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeEvent(string $event, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        echo 'event: '.$event."\n";
        echo 'data: '.$json."\n\n";
        // PHP's built-in server holds small writes. A comment padding forces each step out.
        echo ':'.str_repeat(' ', 8192)."\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private function safeStreamMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        $lower = strtolower($message);
        if (
            $message === ''
            || strlen($message) > 240
            || str_contains($lower, 'api_key')
            || str_contains($lower, 'bearer ')
            || str_contains($lower, 'sqlstate')
        ) {
            return 'The assistant could not finish this question.';
        }

        return $message;
    }
}
