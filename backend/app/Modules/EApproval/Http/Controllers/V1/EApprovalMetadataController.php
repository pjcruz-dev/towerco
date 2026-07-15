<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMetadataController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalMetadataService $metadata): JsonResponse
    {
        abort_unless(
            $request->user()?->can('e_approval:view')
            || $request->user()?->can('e_approval:submissions:create'),
            403,
        );

        return $this->ok($metadata->build());
    }
}
