<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementPoMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoMigrateController extends AbstractApiController
{
    public function __invoke(Request $request, ProcurementPoMigrationService $migration): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:documents:manage'), 403);

        return $this->ok($migration->migrateFromEApprovalSubmissions());
    }
}
