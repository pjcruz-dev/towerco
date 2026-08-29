<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantBackupIndexController extends AbstractApiController
{
    public function __invoke(Request $request, TenantDatabaseBackupService $backups): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        /** @var Tenant $tenant */
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        $result = $backups->listForTenant($tenant, completedOnly: true);

        return $this->okWithMeta($result['data'], $result['meta']);
    }
}
