<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserReactivateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUser $user, TenantUserAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $reactivated = $service->reactivate($user);

        return $this->ok([
            'id' => $reactivated->id,
            'name' => $reactivated->name,
            'email' => $reactivated->email,
            'is_active' => $reactivated->isActive(),
            'roles' => $reactivated->getRoleNames()->values()->all(),
            'message' => __('User reactivated.'),
        ]);
    }
}
