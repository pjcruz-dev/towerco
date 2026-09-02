<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantIntegrationApiKeyService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationApiKeyStoreController extends AbstractApiController
{
    public function __invoke(Request $request, TenantIntegrationApiKeyService $keys): JsonResponse
    {
        abort_unless($request->user()?->can('api_keys:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $created = $keys->create($actor, (string) $data['name']);

        return $this->ok($created, 201);
    }
}
