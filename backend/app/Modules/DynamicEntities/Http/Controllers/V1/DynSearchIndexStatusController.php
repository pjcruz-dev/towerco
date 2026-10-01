<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynSearchIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynSearchIndexStatusController extends AbstractApiController
{
    public function __invoke(Request $request, DynSearchIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('search_index:manage'), 403);

        return $this->ok($service->status());
    }
}
