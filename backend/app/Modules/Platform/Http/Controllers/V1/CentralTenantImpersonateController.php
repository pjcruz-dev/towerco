<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\PlatformTenantImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantImpersonateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        PlatformTenantImpersonationService $service,
    ): JsonResponse {
        $data = $request->validate([
            'user_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($service->start($tenant, $actor, $data['user_id'], $data['reason']));
    }
}
