<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\RoleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\AdminOne\Models\TenantRole;

class RoleUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantRole $role, RoleCatalogService $service): JsonResponse
    {
        abort_unless($request->user()?->can('role:manage'), 403);

        $data = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'max:64'],
        ]);

        $updated = $service->updateCustomRolePermissions($role, $data['permissions']);

        return $this->ok([
            'id' => $updated->id,
            'name' => $updated->name,
            'permissions' => $updated->permissions->pluck('name')->values()->all(),
        ]);
    }
}
