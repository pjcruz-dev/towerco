<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserBulkResetPasswordController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['uuid'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
            'revoke_sessions' => ['sometimes', 'boolean'],
        ]);

        /** @var TenantUser $actor */
        $actor = $request->user();

        return $this->ok($service->bulkResetPasswords(
            $actor,
            $data['user_ids'],
            isset($data['password']) && is_string($data['password']) && $data['password'] !== ''
                ? $data['password']
                : null,
            (bool) ($data['revoke_sessions'] ?? true),
        ));
    }
}
