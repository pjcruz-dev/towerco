<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\SidebarNavService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceSidebarShowController extends AbstractApiController
{
    public function __invoke(Request $request, SidebarNavService $sidebar): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        return $this->ok($sidebar->resolveForUser($user));
    }
}
