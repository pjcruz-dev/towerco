<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Http\Requests\StoreTenantKnowledgeRequest;
use App\Modules\AiAssistant\Services\TenantKnowledgeService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class AssistantKnowledgeStoreController extends AbstractApiController
{
    public function __invoke(StoreTenantKnowledgeRequest $request, TenantKnowledgeService $knowledge): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:knowledge:manage'), 403);

        $source = $knowledge->createDraft($user, $request->validatedPayload());

        return $this->created($knowledge->asDetail($source));
    }
}
