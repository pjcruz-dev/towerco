<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\AiPromptModuleService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPromptModuleIndexController extends AbstractApiController
{
    public function __invoke(Request $request, AiPromptModuleService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:prompts:manage'), 403);

        $rows = $service->list(false);

        return $this->okWithMeta($rows, ['total' => count($rows)]);
    }
}
