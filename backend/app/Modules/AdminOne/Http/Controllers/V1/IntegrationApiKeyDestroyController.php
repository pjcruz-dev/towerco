<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantIntegrationApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationApiKeyDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, int $token, TenantIntegrationApiKeyService $keys): JsonResponse
    {
        abort_unless($request->user()?->can('api_keys:manage'), 403);

        $keys->revoke($token);

        return $this->ok(['revoked' => true]);
    }
}
