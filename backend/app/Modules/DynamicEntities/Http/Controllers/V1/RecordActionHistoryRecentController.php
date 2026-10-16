<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\RecordActionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordActionHistoryRecentController extends AbstractApiController
{
    public function __invoke(Request $request, RecordActionHistoryService $history): JsonResponse
    {
        $user = $request->user();
        $dynamicEntities = (bool) $user?->can('dynamic_entities:view');
        $assets = (bool) $user?->can('assets:view');
        $ticketing = (bool) $user?->can('ticketing:view');
        abort_unless($dynamicEntities || $assets || $ticketing, 403);

        $scope = $request->query('scope') === 'everyone' ? 'everyone' : 'mine';
        $actorId = $user?->id !== null ? (string) $user->id : null;

        return $this->ok($history->listRecent($actorId, $scope, $dynamicEntities, $assets, $ticketing));
    }
}
