<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUser $user, TenantUserAdminService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('user:manage')
            || $request->user()?->can('organization:manage'),
            403,
        );

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email:rfc', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:64'],
        ]);

        $actor = $request->user();
        $rolesOnly = array_keys($data) === ['roles']
            || (array_key_exists('roles', $data) && count($data) === 1);
        if (
            $actor !== null
            && ! $actor->can('user:manage')
            && $actor->can('organization:manage')
            && ! $rolesOnly
        ) {
            abort(403, 'Organization managers may only update roles from the organization chart.');
        }

        $updated = $service->update(
            $user,
            $data['name'] ?? null,
            $data['email'] ?? null,
            $data['roles'] ?? null,
            $data['password'] ?? null,
        );

        return $this->ok([
            'id' => $updated->id,
            'name' => $updated->name,
            'email' => $updated->email,
            'roles' => $updated->getRoleNames()->values()->all(),
        ]);
    }
}
