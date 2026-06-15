<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\PlatformTenantDirectoryService;
use Illuminate\Http\JsonResponse;

final class CentralTenantShowController extends AbstractApiController
{
    public function __invoke(Tenant $tenant, PlatformTenantDirectoryService $directory): JsonResponse
    {
        $row = $directory->show($tenant);

        abort_if($row === null, 404);

        return $this->ok($row);
    }
}
