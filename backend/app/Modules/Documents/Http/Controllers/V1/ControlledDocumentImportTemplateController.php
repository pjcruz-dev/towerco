<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Services\ControlledDocumentImportService;
use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentImportTemplateController
{
    public function __invoke(Request $request, ControlledDocumentImportService $import): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('documents:controlled:import'), 403);
        abort_unless(app(ControlledDocumentRoleFieldAccess::class)->allows($user, 'import'), 403);

        $csv = $import->templateCsv();

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'controlled-document-register-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
