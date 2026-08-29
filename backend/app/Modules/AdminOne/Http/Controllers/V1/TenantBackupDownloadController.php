<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantBackupDownloadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $backup,
        TenantDatabaseBackupService $backups,
    ): StreamedResponse {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        /** @var Tenant $tenant */
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        $row = $backups->findForTenant($tenant, $backup);

        return $backups->streamDownload($tenant, $row);
    }
}
