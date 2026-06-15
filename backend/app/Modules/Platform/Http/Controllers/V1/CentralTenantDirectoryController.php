<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\PlatformTenantDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantDirectoryController extends AbstractApiController
{
    public function index(Request $request, PlatformTenantDirectoryService $directory): JsonResponse
    {
        return $this->ok($directory->list($request));
    }
}
