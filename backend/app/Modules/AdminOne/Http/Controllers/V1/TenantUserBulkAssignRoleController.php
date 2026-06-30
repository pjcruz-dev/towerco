<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantUserBulkAssignRoleController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['uuid'],
            'role' => ['sometimes', 'string', 'max:64'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', 'max:64'],
            'mode' => ['sometimes', 'string', 'in:add,replace'],
            'remove_roles' => ['sometimes', 'array'],
            'remove_roles.*' => ['string', 'max:64'],
        ]);

        $roles = $data['roles'] ?? (isset($data['role']) ? [$data['role']] : []);
        if ($roles === [] && empty($data['remove_roles'])) {
            throw ValidationException::withMessages([
                'roles' => [__('Provide role, roles, or remove_roles.')],
            ]);
        }

        return $this->ok($service->bulkAssignRoles(
            $data['user_ids'],
            $roles,
            $data['mode'] ?? 'add',
            $data['remove_roles'] ?? [],
        ));
    }
}
