<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\AtcTicketingBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AtcTicketingBoardController extends AbstractApiController
{
    public function __invoke(Request $request, AtcTicketingBoardService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        return $this->ok($service->build());
    }
}
