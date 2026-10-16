<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetImportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AssetExportController extends AbstractApiController
{
    public function __invoke(Request $request, AssetImportExportService $imports): Response
    {
        abort_unless($request->user()?->can('assets:export'), 403);
        $format = $request->query('format', 'csv') === 'xlsx' ? 'xlsx' : 'csv';
        $binary = $imports->exportBinary($request->query(), $format);
        $type = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return response($binary, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="assets.'.$format.'"',
        ]);
    }
}
