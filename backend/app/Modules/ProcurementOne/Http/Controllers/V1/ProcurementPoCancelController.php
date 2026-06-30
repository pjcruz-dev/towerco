<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementDocumentLifecycleService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoCancelController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $po,
        ProcurementDocumentLifecycleService $lifecycle,
        ProcurementPoRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $model = $registry->find($po);
        abort_if($model === null, 404);

        $cancelled = $lifecycle->cancelPurchaseOrder($model, $actor, $data['reason'] ?? null);
        $fresh = $registry->find((string) $cancelled->id);

        return $this->ok($registry->toDetailPayload($fresh ?? $cancelled));
    }
}
