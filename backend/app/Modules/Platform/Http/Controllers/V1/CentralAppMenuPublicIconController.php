<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\AppMenuTile;
use App\Modules\Platform\Services\AppMenuIconAssetService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CentralAppMenuPublicIconController extends AbstractApiController
{
    public function __invoke(
        AppMenuTile $appMenuTile,
        AppMenuIconAssetService $icons,
    ): StreamedResponse {
        return $icons->stream($appMenuTile);
    }
}
