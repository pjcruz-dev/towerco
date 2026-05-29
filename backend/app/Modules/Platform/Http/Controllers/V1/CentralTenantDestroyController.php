<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantOffboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantOffboardingService $offboarding,
    ): JsonResponse {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'uuid'],
            'cascade' => ['sometimes', 'boolean'],
        ]);

        $result = $offboarding->deleteTenant($tenant, $data);

        return $this->ok($result);
    }
}
