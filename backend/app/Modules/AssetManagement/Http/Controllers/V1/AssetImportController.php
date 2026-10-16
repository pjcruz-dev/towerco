<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetImportExportService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetImportController extends AbstractApiController
{
    public function __invoke(Request $request, AssetImportExportService $imports): JsonResponse
    {
        abort_unless($request->user()?->can('assets:import'), 403);
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'confirm' => ['required', 'boolean'],
            'partial' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($imports->import($data['rows'], (bool) $data['confirm'], (bool) ($data['partial'] ?? false), AssetActor::from($request)));
    }
}
