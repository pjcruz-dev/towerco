<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantSystemConfigService $service): JsonResponse
    {
        abort_unless($request->user()?->can('system:manage'), 403);

        $data = $request->validate([
            'brand' => ['sometimes', 'array'],
            'theme' => ['sometimes', 'array'],
            'localization' => ['sometimes', 'array'],
            'support' => ['sometimes', 'array'],
            'security' => ['sometimes', 'array'],
            'integrations' => ['sometimes', 'array'],
        ]);

        return $this->ok($service->update($data));
    }
}
