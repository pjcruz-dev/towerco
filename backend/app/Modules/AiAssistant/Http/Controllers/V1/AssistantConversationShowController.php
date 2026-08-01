<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\ConversationService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantConversationShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $conversation,
        ConversationService $conversations,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);

        $model = $conversations->findVisibleOrFail($user, $conversation);

        return $this->ok($conversations->asDetail($model));
    }
}
