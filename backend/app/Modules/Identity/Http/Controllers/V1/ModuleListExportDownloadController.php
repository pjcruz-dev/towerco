<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\ModuleListExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleListExportDownloadController extends AbstractApiController
{
    public function __invoke(Request $request, string $export, ModuleListExportService $exports): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $row = $exports->findForUser($user, $export);
        abort_unless($row->status === \App\Modules\Identity\Models\ModuleListExport::STATUS_COMPLETED, 409, 'Export is not ready.');

        $path = $exports->absolutePath($row);
        abort_unless(is_file($path), 404);

        $contentType = match ((string) $row->format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'html' => 'text/html; charset=UTF-8',
            default => 'text/csv; charset=UTF-8',
        };

        return response()->download($path, (string) $row->filename, [
            'Content-Type' => $contentType,
        ]);
    }
}
