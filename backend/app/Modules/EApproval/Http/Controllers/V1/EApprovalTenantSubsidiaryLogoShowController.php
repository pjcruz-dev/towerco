<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Modules\EApproval\Services\EApprovalFileStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalTenantSubsidiaryLogoShowController
{
    public function __invoke(
        Request $request,
        string $code,
        EApprovalFileStorageService $files,
    ): StreamedResponse {
        abort_unless(
            $request->user()?->can('e_approval:forms:manage')
            || $request->user()?->can('e_approval:submissions:view')
            || $request->user()?->can('e_approval:submissions:create'),
            403,
        );

        return $files->streamTenantSubsidiaryLogo($code);
    }
}
