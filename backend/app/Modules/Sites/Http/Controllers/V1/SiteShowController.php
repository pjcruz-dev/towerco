<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteShowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteShowController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, SiteShowService $service): JsonResponse
    {
        abort_unless($request->user()?->can('sites:view'), 403);

        return $this->ok($service->asDetail($site));
    }
}
