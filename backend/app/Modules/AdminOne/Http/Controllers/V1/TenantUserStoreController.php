<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserStoreController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:64'],
        ]);

        $result = $service->create(
            $data['name'],
            $data['email'],
            $data['roles'] ?? ['viewer'],
            $data['password'] ?? null,
        );

        $user = $result['user'];

        return $this->created([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'generated_password' => $result['generated_password'],
        ]);
    }
}
