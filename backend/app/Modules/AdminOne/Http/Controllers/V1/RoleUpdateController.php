<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Services\RoleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $payload = $service->show($updated);
        unset($payload['users']);

        return $this->ok($payload);
    }
}
