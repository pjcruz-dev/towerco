<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\EApproval\Services\EApprovalMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMasterDataSetDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalMasterDataSet $set, EApprovalMasterDataService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $service->deleteSet($set);

        return $this->ok(['ok' => true]);
    }
}
