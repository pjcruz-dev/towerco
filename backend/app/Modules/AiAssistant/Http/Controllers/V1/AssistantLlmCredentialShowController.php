<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\TenantLlmCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantLlmCredentialShowController extends AbstractApiController
{
    public function __invoke(Request $request, TenantLlmCredentialService $credentials): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        return $this->ok($credentials->snapshot());
    }
}
