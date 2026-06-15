<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalAuditIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalAuditIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, EApprovalAuditIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:80'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $paginator = $service->paginate(
            $query['page'],
            $query['per_page'],
            $validated['action'] ?? null,
            $query['search'],
            isset($validated['from']) ? (string) $validated['from'] : null,
            isset($validated['to']) ? (string) $validated['to'] : null,
        );

        return $this->okWithMeta($service->asPayload($paginator), [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
