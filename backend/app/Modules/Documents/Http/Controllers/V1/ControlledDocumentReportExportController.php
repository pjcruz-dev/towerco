<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentReportExportController
{
    public function __invoke(
        Request $request,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessRegistry($user), 403);

        $data = $request->validate([
            'period' => ['sometimes', 'nullable', 'integer', Rule::in([30, 90, 365])],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'published', 'obsolete'])],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'review' => ['sometimes', 'nullable', 'string', Rule::in(['overdue', 'due_30', 'due_90', 'none'])],
            'file' => ['sometimes', 'nullable', 'string', Rule::in(['has', 'missing'])],
            'published_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'published_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:published_from'],
        ]);

        $csv = $registry->exportCsv($user, $data);

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'controlled-document-register.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
