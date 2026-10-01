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

class AssistantAskController extends AbstractApiController
{
    public function __invoke(AskAssistantRequest $request, AssistantService $service): JsonResponse
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

        $result = $service->ask(
            $user,
            AskAssistantInput::fromValidated($request->validatedPayload()),
        );

        AssistantDailyUsageReporter::recordAsk($request);

        $payload = $result->toArray();
        $payload['rate_limit'] = AssistantRateLimitReporter::forRequest($request);

        return $this->ok($payload);
    }
}
