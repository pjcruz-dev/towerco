<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Billing\Services\PlatformBillingCatalogService;
use App\Modules\Billing\Services\StripeBillingConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralPlatformBillingCatalogUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        PlatformBillingCatalogService $catalog,
        StripeBillingConfig $stripe,
    ): JsonResponse {
        $data = $request->validate([
            'currency' => ['sometimes', 'string', 'in:'.implode(',', array_keys(config('billing.currencies', [])))],
            'default_annual_discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:80'],
            'tiers' => ['sometimes', 'array'],
            'tiers.*.plan_tier' => ['required_with:tiers', 'string', 'in:starter,professional,enterprise'],
            'tiers.*.annual_discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:80'],
            'tiers.*.included' => ['sometimes', 'array'],
            'tiers.*.included.paid_seats' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'tiers.*.included.rfi_units' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'tiers.*.included.storage_gb' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'tiers.*.pricing' => ['sometimes', 'array'],
            'tiers.*.pricing.monthly_base_usd' => ['sometimes', 'numeric', 'min:0'],
            'tiers.*.pricing.rfi_overage_usd' => ['sometimes', 'numeric', 'min:0'],
            'tiers.*.pricing.paid_seat_overage_usd' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $resolved = $catalog->update($data, $request->user());

        return $this->ok([
            ...$resolved,
            'subscription' => [
                'trial_days' => (int) config('billing.subscription.trial_days', 14),
                'past_due_grace_days' => (int) config('billing.subscription.past_due_grace_days', 7),
                'on_trial_expire' => (string) config('billing.subscription.on_trial_expire', 'active'),
            ],
            'payments' => $stripe->publicSnapshot(),
        ]);
    }
}
