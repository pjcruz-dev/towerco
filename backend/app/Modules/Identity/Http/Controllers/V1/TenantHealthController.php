<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use Illuminate\Http\JsonResponse;

class TenantHealthController extends AbstractApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->ok([
            'status' => 'ok',
            'context' => 'tenant',
            'tenant_id' => tenant('id'),
            'version' => config('toweros.api.current_version'),
        ]);
    }

    public function me(): JsonResponse
    {
        $user = request()->user();
        assert($user !== null);

        return $this->ok([
            'id' => $user->getKey(),
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }
}
