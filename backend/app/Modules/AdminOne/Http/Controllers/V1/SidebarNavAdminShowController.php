<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\SidebarNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarNavAdminShowController extends AbstractApiController
{
    public function __invoke(Request $request, SidebarNavService $sidebar): JsonResponse
    {
        abort_unless($request->user()?->can('sidebar:manage'), 403);

        return $this->ok($sidebar->adminTree());
    }
}
