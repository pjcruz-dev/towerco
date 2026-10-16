<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\RecordActionHistoryService;
use App\Modules\DynamicEntities\Support\RecordActionHistoryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordActionHistoryRevertController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, string $history, RecordActionHistoryService $actions): JsonResponse
    {
        $slug = RecordActionHistoryAccess::slug($entity);
        RecordActionHistoryAccess::assertRevert($request, $slug);
        $actor = RecordActionHistoryAccess::actor($request);
        $row = $actions->revert($actions->findForEntity($slug, $history));

        return $this->ok($actions->present($row, (string) $actor->id));
    }
}
