<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\PlatformTenantSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantSettingsController extends AbstractApiController
{
    public function update(
        Request $request,
        Tenant $tenant,
        PlatformTenantSettingsService $settings,
    ): JsonResponse {
        $data = $request->validate([
            'mfa_required' => ['sometimes', 'boolean'],
            'theme_tokens' => ['sometimes', 'nullable', 'array'],
            'plan_tier' => ['sometimes', 'string', 'in:starter,professional,enterprise'],
            'subscription_status' => ['sometimes', 'string', 'in:trial,active,past_due,canceled'],
            'seat_limit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'billing_meter_starts_at' => ['sometimes', 'nullable', 'date'],
            'billing_interval' => ['sometimes', 'string', 'in:monthly,annual'],
            'confirm_plan_downgrade' => ['sometimes', 'boolean'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'past_due_grace_ends_at' => ['sometimes', 'nullable', 'date'],
            'billing_overrides' => ['sometimes', 'nullable', 'array'],
            'enabled_modules' => ['sometimes', 'nullable', 'array'],
            'enabled_modules.*' => ['string', 'max:64'],
            'operator_access_mode' => ['sometimes', 'nullable', 'string', 'in:read_only,blocked'],
        ]);

        /** @var User|null $actor */
        $actor = $request->user();

        return $this->ok($settings->update($tenant, $data, $actor));
    }
}
