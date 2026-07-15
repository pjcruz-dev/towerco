<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementRfqRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementRfqRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'pr_id' => ['sometimes', 'nullable', 'uuid'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $paginator = $registry->paginate(
            max(1, (int) ($data['page'] ?? 1)),
            min(100, max(1, (int) ($data['per_page'] ?? 25))),
            $data['search'] ?? null,
            $data['status'] ?? null,
            $data['pr_id'] ?? null,
            $data['sort'] ?? null,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn ($row) => $registry->toListPayload($row))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
