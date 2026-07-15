<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementVendorRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorIndexController extends AbstractApiController
{
    public function __invoke(Request $request, ProcurementVendorRegistryService $registry): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:vendors:view'), 403);

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $paginator = $registry->paginate(
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 25),
            $data['search'] ?? null,
            $data['status'] ?? null,
            $data['sort'] ?? null,
        );

        $rows = collect($paginator->items())
            ->map(static fn ($vendor) => $registry->toListPayload($vendor))
            ->values()
            ->all();

        return $this->okWithMeta($rows, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
