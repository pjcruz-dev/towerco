<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSubmissionShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicSharedSubmissionShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $token, EApprovalSubmissionShareLinkService $service): JsonResponse
    {
        return $this->ok($service->publicPayload($token));
    }
}
