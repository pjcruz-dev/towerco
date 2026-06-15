<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\EApproval\Services\EApprovalMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMasterDataRowStoreController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalMasterDataSet $set, EApprovalMasterDataService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:255'],
            'data' => ['sometimes', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = $service->createRow($set, $data);

        return $this->ok(['id' => (string) $row->id], 201);
    }
}
