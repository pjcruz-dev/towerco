<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMasterDataLookupController extends AbstractApiController
{
    public function __invoke(Request $request, string $key, EApprovalMasterDataService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('e_approval:view')
            || $request->user()?->can('e_approval:submissions:create'),
            403,
        );

        return $this->ok($service->lookupByKey($key));
    }
}
