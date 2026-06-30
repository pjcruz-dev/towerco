<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementFormComposeService $compose,
        ProcurementPoService $service,
        ProcurementPoRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertModuleEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
                'parent_submission_id' => ['nullable', 'uuid'],
            ]);

            $po = $compose->createPurchaseOrder(
                $data['values'],
                $actor,
                $data['parent_submission_id'] ?? null,
                null,
                requireRequired: false,
            );
            $model = $registry->find((string) $po->id);

            return $this->created($registry->toDetailPayload($model ?? $po));
        }

        $po = $service->create($this->validatedPayload($request), $actor);
        $model = $registry->find((string) $po->id);

        return $this->created($registry->toDetailPayload($model ?? $po));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'pr_ids' => ['required', 'array', 'min:1'],
            'pr_ids.*' => ['required', 'uuid', 'exists:procurement_prs,id'],
            'vendor_code' => ['nullable', 'string', 'max:64'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:500'],
            'ship_to' => ['nullable', 'string', 'max:2000'],
            'delivery_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'vat_exempt_amount' => ['nullable', 'numeric', 'min:0'],
            'zero_rated_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0'],
            'less_discount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.item' => ['nullable', 'string', 'max:120'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.uom' => ['nullable', 'string', 'max:32'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.pr_id' => ['nullable', 'uuid', 'exists:procurement_prs,id'],
            'lines.*.pr_line_id' => ['nullable', 'uuid'],
            'lines.*.cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'lines.*.expense_type' => ['nullable', 'string', 'in:capex,opex'],
            'lines.*.budget_line_id' => ['nullable', 'uuid', 'exists:procurement_budget_lines,id'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.pr_id' => ['required_with:allocations', 'uuid', 'exists:procurement_prs,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0'],
            'contract_id' => ['nullable', 'uuid', 'exists:procurement_contracts,id'],
        ]);
    }
}
