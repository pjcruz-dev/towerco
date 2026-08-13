<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSecuritySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantSecuritySettingsUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantSecuritySettingsService $service): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        $data = $request->validate([
            'mfa_required' => ['required', 'boolean'],
            'mfa_trust_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'passkeys_enabled' => ['sometimes', 'boolean'],
            'passkeys_policy' => ['sometimes', 'string', 'in:allow,prefer,require'],
            'passkeys_satisfies_mfa' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->update($data));
    }
}
