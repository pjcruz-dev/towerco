<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSystemConfigService;
use App\Modules\Platform\Services\TenantBrandingAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigBrandingUploadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $asset,
        TenantSystemConfigService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('system:manage'), 403);

        if (! TenantBrandingAssetService::isKind($asset)) {
            abort(404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:512'],
        ]);

        $file = $request->file('file');
        abort_unless($file !== null, 422);

        return $this->ok($service->uploadBrandingAsset($file, $asset));
    }
}
