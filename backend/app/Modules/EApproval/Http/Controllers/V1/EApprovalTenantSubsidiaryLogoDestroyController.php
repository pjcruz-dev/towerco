<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use App\Modules\EApproval\Services\EApprovalSubsidiaryLogoCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalTenantSubsidiaryLogoDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $code,
        EApprovalSubsidiaryLogoCatalogService $catalog,
        EApprovalAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $clearOnly = $request->boolean('clear_only', false);
        if ($clearOnly) {
            $catalog->clearLogo($code);
            $normalized = strtoupper(trim($code));
        } else {
            $catalog->removeCode($code);
            $normalized = strtoupper(trim($code));
        }

        $presented = $catalog->present();

        $audit->log(
            $clearOnly ? 'tenant_subsidiary_logo_cleared' : 'tenant_subsidiary_code_removed',
            null,
            $normalized,
            $request->user(),
        );

        return $this->ok([
            'code' => $normalized,
            'subsidiary_codes' => $presented['codes'],
            'subsidiary_logos' => $presented['logos'],
        ]);
    }
}
