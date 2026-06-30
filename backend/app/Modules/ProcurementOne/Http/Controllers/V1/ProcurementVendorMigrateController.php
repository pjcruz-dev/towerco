<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementVendorMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorMigrateController extends AbstractApiController
{
    public function __invoke(Request $request, ProcurementVendorMigrationService $migration): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:vendors:manage'), 403);

        return $this->ok($migration->migrateFromMasterData());
    }
}
