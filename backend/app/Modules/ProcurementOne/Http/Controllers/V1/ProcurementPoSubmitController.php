<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPoSubmissionBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoSubmitController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $po,
        ProcurementPoSubmissionBridgeService $bridge,
        ProcurementPoRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($po);
        abort_if($model === null, 404);

        $result = $bridge->submit($model, $actor);
        $fresh = $registry->find((string) $result['po']->id);

        return $this->ok([
            'po' => $registry->toDetailPayload($fresh ?? $result['po']),
            'warning' => $result['warning'],
        ]);
    }
}
