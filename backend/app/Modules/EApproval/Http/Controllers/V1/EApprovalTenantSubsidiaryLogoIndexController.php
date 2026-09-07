<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSubsidiaryLogoCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalTenantSubsidiaryLogoIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubsidiaryLogoCatalogService $catalog,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('e_approval:forms:manage')
            || $request->user()?->can('e_approval:submissions:view')
            || $request->user()?->can('e_approval:submissions:create'),
            403,
        );

        $presented = $catalog->present();

        return $this->ok([
            'subsidiary_codes' => $presented['codes'],
            'subsidiary_logos' => $presented['logos'],
        ]);
    }
}
