<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantBackupRestoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        string $backup,
        TenantDatabaseBackupService $backups,
    ): JsonResponse {
        $data = $request->validate([
            'confirm' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $row = $backups->findForTenant($tenant, $backup);
        $restored = $backups->queueRestore($tenant, $row, $actor, $data['confirm'], $data['reason']);

        return $this->ok($backups->toPayload($restored), status: 202);
    }
}
