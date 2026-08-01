<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\TenantKnowledgeService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantKnowledgeShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $source, TenantKnowledgeService $knowledge): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:knowledge:manage'), 403);

        $model = $knowledge->findTenantSourceOrFail($source);

        return $this->ok($knowledge->asDetail($model));
    }
}
