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
            'access_matrix' => ['sometimes', 'nullable', 'array'],
            'access_matrix.entities' => ['sometimes', 'array'],
            'access_matrix.fields' => ['sometimes', 'array'],
            'access_matrix.workflows' => ['sometimes', 'array'],
            'access_matrix.filters' => ['sometimes', 'array'],
        ]);

        $updated = $service->updateCustomRolePermissions(
            $role,
            $data['permissions'],
            array_key_exists('access_matrix', $data) ? $data['access_matrix'] : null,
        );
        $payload = $service->show($updated);
        unset($payload['users']);

        return $this->ok($payload);
    }
}
