<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CentralTenantBackupDownloadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        string $backup,
        TenantDatabaseBackupService $backups,
    ): StreamedResponse {
        $row = $backups->findForTenant($tenant, $backup);

        return $backups->streamDownload($tenant, $row);
    }
}
