<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\EntraOrgDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantUserEntraOrgSyncController extends AbstractApiController
{
    public function __invoke(Request $request, EntraOrgDirectoryService $org): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        set_time_limit(180);
        ignore_user_abort(true);

        return $this->ok($org->syncDirectoryFromApp());
    }
}
