<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetImportExportService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetImportAnalyzeController extends AbstractApiController
{
    public function __invoke(Request $request, AssetImportExportService $imports): JsonResponse
    {
        abort_unless($request->user()?->can('assets:import'), 403);
        $request->validate(['file' => ['required', 'file']]);
        $file = $request->file('file');

        $actor = $request->user();

        return $this->ok($imports->analyze(
            $file->getClientOriginalName(),
            (string) file_get_contents($file->getRealPath()),
            $actor instanceof TenantUser ? $actor : null,
        ));
    }
}
