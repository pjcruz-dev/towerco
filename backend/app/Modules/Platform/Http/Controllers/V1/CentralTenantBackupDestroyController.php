<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantBackupDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        string $backup,
        TenantDatabaseBackupService $backups,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $row = $backups->findForTenant($tenant, $backup);
        $backups->deleteBackup($tenant, $row, $actor);

        return $this->ok(['deleted' => true]);
    }
}
