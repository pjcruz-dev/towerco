<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use App\Modules\EApproval\Services\EApprovalPdfLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormSubsidiaryCodeStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalPdfLayoutService $pdfLayout,
        EApprovalAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:24'],
        ]);

        $codes = $pdfLayout->registerSubsidiaryCode((string) $form->id, (string) $data['code']);
        $layout = $pdfLayout->show((string) $form->id);
        $template = is_array($layout['template'] ?? null) ? $layout['template'] : [];

        $audit->log(
            'form_subsidiary_code_registered',
            $form->id,
            strtoupper(trim((string) $data['code'])),
            $request->user(),
        );

        return $this->ok([
            'code' => strtoupper(trim((string) $data['code'])),
            'subsidiary_codes' => $codes,
            'subsidiary_logos' => is_array($template['subsidiary_logos'] ?? null)
                ? $template['subsidiary_logos']
                : [],
        ]);
    }
}
