<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Http\Requests\ConfirmAssistantActionRequest;
use App\Modules\AiAssistant\Services\Actions\AssistantActionService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class AssistantActionConfirmController extends AbstractApiController
{
    public function __invoke(ConfirmAssistantActionRequest $request, AssistantActionService $actions): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);
        abort_unless($user->can('ai_assistant:use'), 403);
        abort_unless($user->can('ai_assistant:actions:execute'), 403);
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));
        abort_unless((bool) config('ai_assistant.actions.enabled', true), 403, __('Assistant actions are disabled.'));

        $payload = $request->validatedPayload();
        $confirmed = $actions->confirm(
            $user,
            $payload['proposal_id'],
            $payload['payload'] ?? null,
        );

        return $this->ok($actions->asConfirmResponse($confirmed['proposal'], $confirmed['result']));
    }
}
