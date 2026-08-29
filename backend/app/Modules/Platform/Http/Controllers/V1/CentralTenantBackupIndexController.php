<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantBackupIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantDatabaseBackupService $backups,
    ): JsonResponse {
        $result = $backups->listForTenant($tenant, completedOnly: false);

        return $this->okWithMeta($result['data'], $result['meta']);
    }
}
