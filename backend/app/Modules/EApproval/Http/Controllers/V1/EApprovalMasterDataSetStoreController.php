<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMasterDataSetStoreController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalMasterDataService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:20'],
            'config_json' => ['sometimes', 'array'],
        ]);

        $set = $service->createSet($data);

        return $this->ok(['id' => (string) $set->id, 'key' => $set->key], 201);
    }
}
