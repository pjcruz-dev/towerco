<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Billing\Services\StripeBillingConfig;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use Illuminate\Http\JsonResponse;

class CentralTenantPlanCatalogController extends AbstractApiController
{
    public function __invoke(
        TenantPlanEntitlementsService $entitlements,
        StripeBillingConfig $stripe,
    ): JsonResponse {
        return $this->ok([
            ...$entitlements->catalog(),
            'subscription' => [
                'trial_days' => (int) config('billing.subscription.trial_days', 14),
                'past_due_grace_days' => (int) config('billing.subscription.past_due_grace_days', 7),
                'on_trial_expire' => (string) config('billing.subscription.on_trial_expire', 'active'),
            ],
            'payments' => $stripe->publicSnapshot(),
        ]);
    }
}
