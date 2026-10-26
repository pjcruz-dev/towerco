<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\TenantEnvironmentClone;
use App\Modules\Tenancy\Services\TenantEnvironmentCloneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantEnvironmentCloneCopyAgainController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantEnvironmentClone $clone,
        TenantEnvironmentCloneService $clones,
    ): JsonResponse {
        $data = $request->validate([
            'confirm_domain' => ['required', 'string', 'max:255'],
            'pause_source' => ['sometimes', 'boolean'],
        ]);

        $owned = $clones->findForTenant($tenant, (string) $clone->id);
        $actor = $request->user();
        $next = $clones->copyAgain(
            $tenant,
            $owned,
            (bool) ($data['pause_source'] ?? false),
            (string) $data['confirm_domain'],
            $actor?->getKey() !== null ? (string) $actor->getKey() : null,
            $actor?->email,
        );

        return $this->ok($clones->present($next), 202);
    }
}
