<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Services\EApprovalMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMasterDataRowUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalMasterDataRow $row, EApprovalMasterDataService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:120'],
            'label' => ['sometimes', 'string', 'max:255'],
            'data' => ['sometimes', 'nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $service->updateRow($row, $data);

        return $this->ok(['ok' => true]);
    }
}
