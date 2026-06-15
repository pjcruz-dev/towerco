<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalPublicFormLink;
use App\Modules\EApproval\Services\EApprovalPublicFormLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicFormLinkRevokeController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalPublicFormLink $publicLink,
        EApprovalPublicFormLinkService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $link = $service->revoke($publicLink);

        return $this->ok($link->toAdminRow());
    }
}
