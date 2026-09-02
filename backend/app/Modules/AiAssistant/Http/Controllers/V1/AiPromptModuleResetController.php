<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Models\AiPromptModule;
use App\Modules\AiAssistant\Services\AiPromptModuleService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPromptModuleResetController extends AbstractApiController
{
    public function __invoke(Request $request, AiPromptModule $module, AiPromptModuleService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:prompts:manage'), 403);

        return $this->ok($service->resetToDefault($module, $user));
    }
}
