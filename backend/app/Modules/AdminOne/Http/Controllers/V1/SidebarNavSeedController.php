<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\SidebarNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarNavSeedController extends AbstractApiController
{
    public function __invoke(Request $request, SidebarNavService $sidebar): JsonResponse
    {
        abort_unless($request->user()?->can('sidebar:manage'), 403);

        $data = $request->validate([
            'force' => ['nullable', 'boolean'],
        ]);

        $sidebar->ensureSeeded((bool) ($data['force'] ?? false));

        return $this->ok($sidebar->adminTree());
    }
}
