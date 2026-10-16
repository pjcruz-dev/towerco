<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\RecordActionHistoryService;
use App\Modules\DynamicEntities\Support\RecordActionHistoryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordActionHistoryIndexController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, RecordActionHistoryService $history): JsonResponse
    {
        $slug = RecordActionHistoryAccess::slug($entity);
        RecordActionHistoryAccess::assertView($request, $slug);
        $scope = $request->query('scope') === 'everyone' ? 'everyone' : 'mine';
        $actor = $request->user();

        return $this->ok($history->list($slug, $actor?->id !== null ? (string) $actor->id : null, $scope));
    }
}
