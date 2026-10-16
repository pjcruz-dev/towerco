<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetFileStorageService;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetDocumentStoreController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetFileStorageService $files): JsonResponse
    {
        abort_unless($request->user()?->can('assets:repair') || $request->user()?->can('assets:manage'), 403);
        $request->validate([
            'file' => ['required', 'file'],
            'repair_id' => ['nullable', 'uuid'],
        ]);
        $document = $files->store($registry->findByTagOrId($asset), $request->file('file'), AssetActor::from($request), $request->input('repair_id'));

        return $this->created($document->toArray());
    }
}
