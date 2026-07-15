<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Billing\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantBillingPortalSessionStoreController extends AbstractApiController
{
    public function __invoke(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()?->can('billing:manage'), 403);

        $tenantKey = (string) tenant('id');
        $central = Tenant::query()->findOrFail($tenantKey);

        return $this->ok($stripe->createPortalSession($central));
    }
}
