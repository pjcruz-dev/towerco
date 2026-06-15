<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFormImportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalFormExportController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalFormImportExportService $export,
    ): StreamedResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $envelope = $export->exportEnvelope($form);
        $filename = 'form-'.preg_replace('/[^a-z0-9_-]+/i', '-', $form->name).'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            static function () use ($envelope): void {
                echo json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
