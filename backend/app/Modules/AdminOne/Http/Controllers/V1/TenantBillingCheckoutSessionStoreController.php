<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Billing\Services\StripeBillingService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantBillingCheckoutSessionStoreController extends AbstractApiController
{
    public function __invoke(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()?->can('billing:manage'), 403);

        $validated = $request->validate([
            'plan_tier' => ['required', 'string', 'in:starter,professional,enterprise'],
        ]);

        $tenantKey = (string) tenant('id');
        $central = Tenant::query()->findOrFail($tenantKey);
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        return $this->ok($stripe->createCheckoutSession(
            $central,
            (string) $validated['plan_tier'],
            $actor,
        ));
    }
}
