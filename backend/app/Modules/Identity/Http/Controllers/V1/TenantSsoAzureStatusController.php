<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\TenantAuthPolicyService;
use App\Modules\Identity\Services\TenantSsoConfigService;
use Illuminate\Http\JsonResponse;

/**
 * Public: whether Microsoft sign-in is enabled for the resolved tenant host.
 */
final class TenantSsoAzureStatusController extends AbstractApiController
{
    public function __invoke(
        TenantSsoConfigService $service,
        TenantAuthPolicyService $authPolicy,
    ): JsonResponse {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return $this->ok([
                'microsoft_sign_in' => null,
                'password_login' => null,
            ]);
        }

        $tenantId = (string) $tenantId;

        return $this->ok([
            'microsoft_sign_in' => $service->publicStatus($tenantId),
            'password_login' => $authPolicy->publicPasswordLoginStatus($tenantId),
        ]);
    }
}
