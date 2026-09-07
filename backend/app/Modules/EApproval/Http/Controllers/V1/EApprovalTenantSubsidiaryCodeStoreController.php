<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use App\Modules\EApproval\Services\EApprovalSubsidiaryLogoCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalTenantSubsidiaryCodeStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubsidiaryLogoCatalogService $catalog,
        EApprovalAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:24'],
        ]);

        $codes = $catalog->registerCode((string) $data['code']);
        $presented = $catalog->present();

        $audit->log(
            'tenant_subsidiary_code_registered',
            null,
            strtoupper(trim((string) $data['code'])),
            $request->user(),
        );

        return $this->ok([
            'code' => strtoupper(trim((string) $data['code'])),
            'subsidiary_codes' => $codes,
            'subsidiary_logos' => $presented['logos'],
        ]);
    }
}
