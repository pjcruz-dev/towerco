<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserImpersonationService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Support\TenantImpersonationContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserImpersonateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantUser $user,
        TenantUserImpersonationService $service,
        TenantImpersonationContextResolver $impersonationResolver,
    ): JsonResponse {
        abort_unless($request->user()?->can('user:impersonate'), 403);

        if ($impersonationResolver->fromRequest($request) !== null) {
            return $this->error(__('End the current impersonation session before starting another.'), 409);
        }

        /** @var TenantUser $actor */
        $actor = $request->user();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return $this->ok($service->start($actor, $user, $data['reason']));
    }
}
