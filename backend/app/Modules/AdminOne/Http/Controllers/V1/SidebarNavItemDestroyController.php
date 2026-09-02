<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Models\SidebarNavItem;
use App\Modules\AdminOne\Services\SidebarNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarNavItemDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        SidebarNavItem $item,
        SidebarNavService $sidebar,
    ): JsonResponse {
        abort_unless($request->user()?->can('sidebar:manage'), 403);

        $sidebar->destroy($item);

        return $this->ok(['deleted' => true]);
    }
}
