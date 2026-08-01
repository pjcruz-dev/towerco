<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Http\Requests\UpdateTenantKnowledgeRequest;
use App\Modules\AiAssistant\Services\TenantKnowledgeService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AssistantKnowledgeUpdateController extends AbstractApiController
{
    public function __invoke(
        UpdateTenantKnowledgeRequest $request,
        string $source,
        TenantKnowledgeService $knowledge,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:knowledge:manage'), 403);

        $model = $knowledge->findTenantSourceOrFail($source);

        try {
            $updated = $knowledge->updateDraft($user, $model, $request->validatedPayload());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->ok($knowledge->asDetail($updated));
    }
}
