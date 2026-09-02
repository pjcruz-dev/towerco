<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynPdfFormStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynPdfFormIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DynPdfFormStorageService $storage): JsonResponse
    {
        abort_unless($request->user()?->can('printables:manage'), 403);

        $rows = $storage->listMerged();

        return $this->okWithMeta($rows, [
            'total' => count($rows),
            'available' => count(array_filter($rows, static fn (array $row): bool => (bool) ($row['available'] ?? false))),
        ]);
    }
}
