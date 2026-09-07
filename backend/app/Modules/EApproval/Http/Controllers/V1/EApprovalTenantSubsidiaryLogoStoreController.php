<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalSubsidiaryLogoCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalTenantSubsidiaryLogoStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $code,
        EApprovalFileStorageService $storage,
        EApprovalSubsidiaryLogoCatalogService $catalog,
        EApprovalAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $result = $storage->storeTenantSubsidiaryLogo($code, $data['file']);
        $catalog->setLogoPath($result['code'], $result['logo_path']);
        $presented = $catalog->present();

        $audit->log(
            'tenant_subsidiary_logo_updated',
            null,
            $result['code'].': '.$result['logo_url'],
            $request->user(),
        );

        return $this->ok([
            'code' => $result['code'],
            'logo_url' => $result['logo_url'],
            'subsidiary_codes' => $presented['codes'],
            'subsidiary_logos' => $presented['logos'],
        ]);
    }
}
