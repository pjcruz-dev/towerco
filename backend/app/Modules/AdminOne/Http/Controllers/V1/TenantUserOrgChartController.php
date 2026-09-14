<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\EntraOrgDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantUserOrgChartController extends AbstractApiController
{
    public function __invoke(Request $request, EntraOrgDirectoryService $org): JsonResponse
    {
        abort_unless(
            $request->user()?->can('organization:view')
            || $request->user()?->can('organization:manage')
            || $request->user()?->can('user:manage'),
            403,
        );

        return $this->ok($org->orgChart());
    }
}
