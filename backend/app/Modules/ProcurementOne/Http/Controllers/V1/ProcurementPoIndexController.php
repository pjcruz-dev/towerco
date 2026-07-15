<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementDocumentScopeService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoIndexController extends AbstractApiController
{
    public function __invoke(Request $request, ProcurementPoRegistryService $registry, ProcurementDocumentScopeService $scope): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:view'), 403);

        /** @var TenantUser $user */
        $user = $request->user();

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mine' => ['sometimes', 'boolean'],
            'pr_id' => ['sometimes', 'nullable', 'uuid'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $requestorId = $scope->requestorIdForIndex($user, (bool) ($data['mine'] ?? false));

        $paginator = $registry->paginate(
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 25),
            $data['search'] ?? null,
            $data['status'] ?? null,
            $requestorId,
            $data['pr_id'] ?? null,
            $data['sort'] ?? null,
        );

        $rows = collect($paginator->items())
            ->map(static fn ($po) => $registry->toListPayload($po))
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
