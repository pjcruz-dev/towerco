<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementCostCenterService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementDocumentTypeCatalog;
use App\Modules\ProcurementOne\Support\ProcurementExpenseType;
use App\Modules\ProcurementOne\Support\ProcurementNumberingSeriesCatalog;
use App\Modules\ProcurementOne\Support\ProcurementStatusCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementOneMetadataController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementDocumentTypeCatalog $documentTypes,
        ProcurementStatusCatalog $statusCatalogs,
        ProcurementNumberingSeriesCatalog $numberingSeries,
        ProcurementOnePlanFeaturesService $planFeatures,
        ProcurementCostCenterService $costCenters,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertModuleEnabled();

        return $this->ok([
            'document_types' => $documentTypes->resolve(),
            'status_catalogs' => $statusCatalogs->resolveAll(),
            'numbering_series' => $numberingSeries->resolveAll(),
            'reset_rules' => ['never', 'yearly', 'monthly'],
            'plan_features' => $planFeatures->snapshot(),
            'cost_centers' => $costCenters->listActive(),
            'expense_types' => array_map(
                static fn (string $type) => [
                    'value' => $type,
                    'label' => ProcurementExpenseType::label($type),
                ],
                ProcurementExpenseType::all(),
            ),
        ]);
    }
}
