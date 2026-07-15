<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPrSubmissionBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrSubmitController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $pr,
        ProcurementPrSubmissionBridgeService $bridge,
        ProcurementPrRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($pr);
        abort_if($model === null, 404);

        $result = $bridge->submit($model, $actor);
        $fresh = $registry->find((string) $result['pr']->id);

        return $this->ok([
            'pr' => $registry->toDetailPayload($fresh ?? $result['pr']),
            'warning' => $result['warning'],
        ]);
    }
}
