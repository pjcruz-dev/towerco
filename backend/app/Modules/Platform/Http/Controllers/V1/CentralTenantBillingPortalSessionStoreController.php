<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Billing\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;

final class CentralTenantBillingPortalSessionStoreController extends AbstractApiController
{
    public function __invoke(Tenant $tenant, StripeBillingService $billing): JsonResponse
    {
        return $this->ok($billing->createPortalSession($tenant));
    }
}
