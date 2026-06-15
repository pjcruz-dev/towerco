<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\PlatformTenantAuditLogger;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Tenancy\Services\TenantOffboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantOffboardingService $offboarding,
        PlatformTenantAuditLogger $audit,
    ): JsonResponse {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'uuid'],
            'cascade' => ['sometimes', 'boolean'],
        ]);

        $tenant->loadMissing('domains');
        $domains = $tenant->domains->pluck('domain')->values()->all();

        /** @var User|null $actor */
        $actor = $request->user();

        $audit->log(
            PlatformTenantAuditEventType::TENANT_DELETED,
            $tenant,
            $actor,
            null,
            [
                'slug' => $tenant->slug,
                'environment' => $tenant->environment,
                'domains' => $domains,
                'cascade' => (bool) ($data['cascade'] ?? false),
            ],
        );

        $result = $offboarding->deleteTenant($tenant, $data);

        return $this->ok($result);
    }
}
