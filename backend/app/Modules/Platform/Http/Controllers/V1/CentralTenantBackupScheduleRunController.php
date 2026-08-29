<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantBackupScheduleRunController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantDatabaseBackupService $backups,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $backup = $backups->queueCreate($tenant, $actor, 'Cron Sync (manual)', \App\Models\TenantDatabaseBackup::TRIGGER_SCHEDULER);

        return $this->ok($backups->toPayload($backup), status: 202);
    }
}
