<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\RoleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RoleCatalogService $service): JsonResponse
    {
        abort_unless($request->user()?->can('role:manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'max:64'],
        ]);

        $role = $service->createCustomRole($data['name'], $data['permissions']);
        $payload = $service->show($role);
        unset($payload['users']);

        return $this->created($payload);
    }
}
