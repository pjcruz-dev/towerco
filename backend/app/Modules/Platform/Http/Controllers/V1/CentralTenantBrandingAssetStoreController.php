<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\TenantBrandingAssetService;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CentralTenantBrandingAssetStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        string $asset,
        TenantBrandingAssetService $assets,
    ): JsonResponse {
        if (! TenantBrandingAssetService::isKind($asset)) {
            throw new NotFoundHttpException;
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:512'],
        ]);

        /** @var User|null $actor */
        $actor = $request->user();

        $tokens = $assets->store($tenant, $data['file'], $asset, $actor);

        return $this->ok([
            'theme_tokens' => TenantThemeTokensValidator::sanitizeForPublic($tokens),
        ]);
    }
}
