<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserBulkDeactivateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['uuid'],
        ]);

        /** @var TenantUser $actor */
        $actor = $request->user();

        return $this->ok($service->bulkDeactivate($actor, $data['user_ids']));
    }
}
