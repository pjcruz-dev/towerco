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

        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'subsidiary' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        return $this->ok($service->lookupByKey(
            $key,
            isset($data['q']) ? (string) $data['q'] : null,
            (int) ($data['limit'] ?? 10),
            isset($data['subsidiary']) ? (string) $data['subsidiary'] : null,
        ));
    }
}
