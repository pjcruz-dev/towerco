<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantBackupUploadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantDatabaseBackupService $backups,
    ): JsonResponse {
        $maxKb = max(1024, (int) config('toweros.tenant_database_backup.upload_max_kb', 32768));

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $backup = $backups->importUploadedArchive(
            $tenant,
            $data['file'],
            $actor,
            $data['reason'] ?? null,
        );

        return $this->ok($backups->toPayload($backup), status: 201);
    }
}
