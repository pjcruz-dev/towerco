<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetImportExportService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetImportRunController extends AbstractApiController
{
    public function __invoke(Request $request, AssetImportExportService $imports): JsonResponse
    {
        abort_unless($request->user()?->can('assets:import'), 403);
        $data = $request->validate([
            'file' => ['required', 'file'],
            'column_map' => ['required', 'string'],
            'upsert_field' => ['nullable', 'string', 'max:128'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $map = json_decode((string) $data['column_map'], true);
        if (! is_array($map)) {
            abort(422, 'Column map must be a JSON object.');
        }
        /** @var array<string, string> $columnMap */
        $columnMap = [];
        foreach ($map as $header => $target) {
            if (is_string($header) && (is_string($target) || is_numeric($target))) {
                $columnMap[$header] = (string) $target;
            }
        }
        $file = $request->file('file');

        return $this->ok($imports->importMapped(
            $file->getClientOriginalName(),
            (string) file_get_contents($file->getRealPath()),
            $columnMap,
            isset($data['upsert_field']) ? (string) $data['upsert_field'] : null,
            $request->boolean('dry_run'),
            AssetActor::from($request),
        ));
    }
}
