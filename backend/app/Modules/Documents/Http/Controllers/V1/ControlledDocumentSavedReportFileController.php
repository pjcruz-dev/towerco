<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentSavedReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentSavedReportFileController
{
    public function __invoke(
        Request $request,
        string $report,
        ControlledDocumentSavedReportService $reports,
        ControlledDocumentAccessService $access,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessRegistry($user), 403);

        $saved = $reports->findForUser($user, $report);
        $csv = $reports->storedFile($user, $saved);
        $filename = $saved->last_export_filename ?: 'controlled-document-register.csv';

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
