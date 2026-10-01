<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\SidebarNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarNavReorderController extends AbstractApiController
{
    public function __invoke(Request $request, SidebarNavService $sidebar): JsonResponse
    {
        abort_unless($request->user()?->can('sidebar:manage'), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'uuid', 'exists:sidebar_nav_items,id'],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['uuid', 'exists:sidebar_nav_items,id'],
        ]);

        $sidebar->reorder(
            $data['ordered_ids'],
            $data['parent_id'] ?? null,
        );

        return $this->ok(['reordered' => true]);
    }
}
