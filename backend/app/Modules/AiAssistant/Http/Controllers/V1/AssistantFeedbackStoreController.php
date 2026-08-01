<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Http\Requests\StoreAssistantFeedbackRequest;
use App\Modules\AiAssistant\Services\FeedbackService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class AssistantFeedbackStoreController extends AbstractApiController
{
    public function __invoke(StoreAssistantFeedbackRequest $request, FeedbackService $feedback): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);

        return $this->ok($feedback->submit($user, $request->validatedPayload()));
    }
}
