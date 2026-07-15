<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Services\RoleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, TenantRole $role, RoleCatalogService $service): JsonResponse
    {
        abort_unless($request->user()?->can('role:manage'), 403);

        $service->deleteCustomRole($role);

        return $this->ok(['deleted' => true]);
    }
}
