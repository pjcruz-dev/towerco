<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentSavedReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentSavedReportRunController
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
        $csv = $reports->csvFor($user, $saved);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $saved->name) ?: 'report';

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
