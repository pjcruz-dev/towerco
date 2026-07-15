<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalFormLogoShowController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalFileStorageService $files,
    ): StreamedResponse {
        abort_unless(
            $request->user()?->can('e_approval:forms:manage')
            || $request->user()?->can('e_approval:submissions:view')
            || $request->user()?->can('e_approval:submissions:create'),
            403,
        );

        return $files->streamFormLogo($form);
    }
}
