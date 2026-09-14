<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalPdfLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormSubsidiaryLogoDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        string $code,
        EApprovalFileStorageService $storage,
        EApprovalPdfLayoutService $pdfLayout,
        EApprovalAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $normalized = strtoupper(trim($code));
        $storage->deleteFormSubsidiaryLogo($form, $normalized);
        $pdfLayout->clearSubsidiaryLogoPath((string) $form->id, $normalized);

        $audit->log(
            'form_subsidiary_logo_cleared',
            $form->id,
            $normalized,
            $request->user(),
        );

        $layout = $pdfLayout->show((string) $form->id);
        $template = is_array($layout['template'] ?? null) ? $layout['template'] : [];

        return $this->ok([
            'code' => $normalized,
            'subsidiary_codes' => is_array($template['subsidiary_codes'] ?? null)
                ? $template['subsidiary_codes']
                : [],
            'subsidiary_logos' => is_array($template['subsidiary_logos'] ?? null)
                ? $template['subsidiary_logos']
                : [],
        ]);
    }
}
