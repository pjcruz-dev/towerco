<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFormOutboundFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormOutboundFileIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalFormOutboundFileService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        return $this->ok($service->listForForm($form));
    }
}
