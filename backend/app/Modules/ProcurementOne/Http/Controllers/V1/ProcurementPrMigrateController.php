<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementPrMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrMigrateController extends AbstractApiController
{
    public function __invoke(Request $request, ProcurementPrMigrationService $migration): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:documents:manage'), 403);

        return $this->ok($migration->migrateFromEApprovalSubmissions());
    }
}
