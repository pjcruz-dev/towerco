<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalPdfLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPdfLayoutShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $formId, EApprovalPdfLayoutService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        return $this->ok($service->show($formId));
    }
}
