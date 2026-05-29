<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutProgramIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutProgramIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, RolloutProgramIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $filters = $this->validatedRolloutIndexFilters($request);

        $paginator = $service->paginate($query['page'], $query['per_page'], array_merge($filters, [
            'search' => $query['search'],
        ]));
        $payload = $service->asPayload($paginator);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }

    /**
     * @return array{status?: string, mno?: string, project_type?: string, region?: string, sort?: string, sla_at_risk?: bool}
     */
    private function validatedRolloutIndexFilters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:32'],
            'mno' => ['sometimes', 'string', 'max:32'],
            'project_type' => ['sometimes', 'string', 'max:32'],
            'region' => ['sometimes', 'string', 'max:64'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'sla_at_risk' => ['sometimes', 'boolean'],
        ]);

        $filters = array_filter([
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'mno' => isset($validated['mno']) ? (string) $validated['mno'] : null,
            'project_type' => isset($validated['project_type']) ? (string) $validated['project_type'] : null,
            'region' => isset($validated['region']) ? (string) $validated['region'] : null,
            'sort' => isset($validated['sort']) ? (string) $validated['sort'] : null,
        ], static fn (?string $value) => $value !== null && $value !== '');

        if ($request->boolean('sla_at_risk')) {
            $filters['sla_at_risk'] = true;
        }

        return $filters;
    }
}
