<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\RecordActionHistoryService;
use App\Modules\DynamicEntities\Support\RecordActionHistoryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordActionHistoryRedoController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, RecordActionHistoryService $actions): JsonResponse
    {
        $slug = RecordActionHistoryAccess::slug($entity);
        RecordActionHistoryAccess::assertRevert($request, $slug);
        $actor = RecordActionHistoryAccess::actor($request);

        return $this->ok($actions->present($actions->redo($slug, $actor), (string) $actor->id));
    }
}
