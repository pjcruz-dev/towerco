<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementFormComposeService $compose,
        ProcurementPrService $service,
        ProcurementPrRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertModuleEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
            ]);

            $pr = $compose->createPurchaseRequisition($data['values'], $actor, requireRequired: false);
            $model = $registry->find((string) $pr->id);

            return $this->created($registry->toDetailPayload($model ?? $pr));
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:64'],
            'urgency' => ['nullable', 'string', 'max:32'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'currency' => ['nullable', 'string', 'max:8'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'rollout_id' => ['nullable', 'uuid', 'exists:rollout_programs,id'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'boq_line_id' => ['nullable', 'uuid'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'lines.*.expense_type' => ['nullable', 'string', 'in:capex,opex'],
            'lines.*.budget_line_id' => ['nullable', 'uuid', 'exists:procurement_budget_lines,id'],
        ]);

        $pr = $service->create($data, $actor);
        $model = $registry->find((string) $pr->id);

        return $this->created($registry->toDetailPayload($model ?? $pr));
    }
}
