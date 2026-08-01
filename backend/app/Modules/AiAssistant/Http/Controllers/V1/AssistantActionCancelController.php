<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\Actions\AssistantActionService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantActionCancelController extends AbstractApiController
{
    public function __invoke(Request $request, string $proposal, AssistantActionService $actions): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);

        $cancelled = $actions->cancel($user, $proposal);

        return $this->ok($actions->asApiPayload($cancelled));
    }
}
