<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetDisposalStoreController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:dispose'), 403);
        $data = $request->validate([
            'disposition' => ['required', 'string', 'max:32'],
            'disposition_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric'],
            'buyer_vendor' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string'],
            'confirm' => ['required', 'accepted'],
        ]);

        return $this->created($workflow->dispose($registry->findByTagOrId($asset), AssetActor::from($request), $data)->toArray());
    }
}
