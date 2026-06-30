<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $pr,
        ProcurementFormComposeService $compose,
        ProcurementPrService $service,
        ProcurementPrRegistryService $registry,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('procurement_one:documents:create')
            || $request->user()?->can('procurement_one:documents:manage'),
            403,
        );

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($pr);
        abort_if($model === null, 404);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
            ]);

            $updated = $compose->updatePurchaseRequisition($model, $data['values'], $actor, requireRequired: false);
            $fresh = $registry->find((string) $updated->id);

            return $this->ok($registry->toDetailPayload($fresh ?? $updated));
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:64'],
            'urgency' => ['sometimes', 'nullable', 'string', 'max:32'],
            'justification' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:8'],
            'project_id' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
            'rollout_id' => ['sometimes', 'nullable', 'uuid', 'exists:rollout_programs,id'],
            'site_id' => ['sometimes', 'nullable', 'uuid', 'exists:sites,id'],
            'boq_line_id' => ['sometimes', 'nullable', 'uuid'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'lines.*.expense_type' => ['nullable', 'string', 'in:capex,opex'],
            'lines.*.budget_line_id' => ['nullable', 'uuid', 'exists:procurement_budget_lines,id'],
        ]);

        $updated = $service->update($model, $data, $actor);
        $fresh = $registry->find((string) $updated->id);

        return $this->ok($registry->toDetailPayload($fresh ?? $updated));
    }
}
