<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantAppearanceService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TenantAppearanceModulesUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantAppearanceService $service): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*' => ['array'],
            'modules.*.primary_preset' => ['required', 'string', Rule::in(['violet', 'teal', 'rose', 'amber', 'blue', 'custom'])],
            'modules.*.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach (array_keys($data['modules']) as $moduleId) {
            abort_unless(in_array($moduleId, TenantThemeTokensValidator::MODULE_ACCENT_IDS, true), 422);
        }

        return $this->ok($service->updateModuleAccents($data['modules'], $actor));
    }
}
