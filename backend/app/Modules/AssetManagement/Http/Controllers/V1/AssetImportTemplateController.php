<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetImportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AssetImportTemplateController extends AbstractApiController
{
    public function __invoke(Request $request, AssetImportExportService $imports): Response
    {
        abort_unless($request->user()?->can('assets:import'), 403);
        if ($request->query('format') === 'csv') {
            return response($imports->csvTemplate(), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="it-asset-import-template.csv"',
            ]);
        }

        return response($imports->templateBinary(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="asset-import-template.xlsx"',
        ]);
    }
}
