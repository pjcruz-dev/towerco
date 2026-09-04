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

class EApprovalFormSubsidiaryLogoStoreController extends AbstractApiController
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

        $data = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $result = $storage->storeFormSubsidiaryLogo($form, $code, $data['file']);
        $pdfLayout->setSubsidiaryLogoPath((string) $form->id, $result['code'], $result['logo_path']);

        $layout = $pdfLayout->show((string) $form->id);
        $template = is_array($layout['template'] ?? null) ? $layout['template'] : [];

        $audit->log(
            'form_subsidiary_logo_updated',
            $form->id,
            $result['code'].': '.$result['logo_url'],
            $request->user(),
        );

        return $this->ok([
            'code' => $result['code'],
            'logo_url' => $result['logo_url'],
            'subsidiary_codes' => is_array($template['subsidiary_codes'] ?? null)
                ? $template['subsidiary_codes']
                : [$result['code']],
            'subsidiary_logos' => is_array($template['subsidiary_logos'] ?? null)
                ? $template['subsidiary_logos']
                : [$result['code'] => $result['logo_url']],
        ]);
    }
}
