<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Services\ControlledDocumentImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentImportTemplateController
{
    public function __invoke(Request $request, ControlledDocumentImportService $import): StreamedResponse
    {
        abort_unless($request->user()?->can('documents:controlled:import'), 403);

        $csv = $import->templateCsv();

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'controlled-document-register-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
