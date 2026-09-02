<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Models\AiPromptModule;
use App\Modules\AiAssistant\Services\AiPromptModuleService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPromptModuleUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, AiPromptModule $module, AiPromptModuleService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:prompts:manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'body' => ['sometimes', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        return $this->ok($service->update($module, $data, $user));
    }
}
