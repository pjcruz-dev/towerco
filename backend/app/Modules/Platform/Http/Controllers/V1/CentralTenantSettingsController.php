<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantSettingsController extends AbstractApiController
{
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'mfa_required' => ['sometimes', 'boolean'],
            'theme_tokens' => ['sometimes', 'nullable', 'array'],
            'plan_tier' => ['sometimes', 'string', 'in:starter,professional,enterprise'],
            'subscription_status' => ['sometimes', 'string', 'in:trial,active,past_due,canceled'],
            'seat_limit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        if (array_key_exists('mfa_required', $data)) {
            $tenant->mfa_required = $data['mfa_required'];
        }

        if (array_key_exists('theme_tokens', $data)) {
            if ($data['theme_tokens'] === null) {
                $tenant->theme_tokens = null;
            } else {
                $tenant->theme_tokens = TenantThemeTokensValidator::validate($data['theme_tokens']);
            }
        }

        if (array_key_exists('plan_tier', $data)) {
            $tenant->plan_tier = $data['plan_tier'];
        }
        if (array_key_exists('subscription_status', $data)) {
            $tenant->subscription_status = $data['subscription_status'];
        }
        if (array_key_exists('seat_limit', $data)) {
            $tenant->seat_limit = $data['seat_limit'];
        }

        $tenant->save();

        return $this->ok([
            'tenant_id' => $tenant->id,
            'mfa_required' => (bool) $tenant->mfa_required,
            'plan_tier' => (string) $tenant->plan_tier,
            'subscription_status' => (string) $tenant->subscription_status,
            'seat_limit' => (int) $tenant->seat_limit,
            'theme_tokens' => $tenant->theme_tokens !== null
                ? TenantThemeTokensValidator::sanitizeForPublic($tenant->theme_tokens)
                : null,
        ]);
    }
}
