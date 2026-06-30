<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Services\RoleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleCloneController extends AbstractApiController
{
    public function __invoke(Request $request, TenantRole $role, RoleCatalogService $service): JsonResponse
    {
        abort_unless($request->user()?->can('role:manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $cloned = $service->cloneRole($role, $data['name']);
        $payload = $service->show($cloned);
        unset($payload['users']);

        return $this->created($payload);
    }
}
