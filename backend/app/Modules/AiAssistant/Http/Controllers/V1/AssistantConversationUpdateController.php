<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Http\Requests\UpdateAssistantConversationRequest;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Services\ConversationService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class AssistantConversationUpdateController extends AbstractApiController
{
    public function __invoke(
        UpdateAssistantConversationRequest $request,
        string $conversation,
        ConversationService $conversations,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        $model = AiConversation::query()->find($conversation);
        abort_if($model === null, 404, __('Conversation not found.'));

        $updated = $conversations->updateTitle($user, $model, $request->title());

        return $this->ok($conversations->asListRow($updated));
    }
}
