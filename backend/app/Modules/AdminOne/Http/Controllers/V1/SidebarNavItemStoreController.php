<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\SidebarNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarNavItemStoreController extends AbstractApiController
{
    public function __invoke(Request $request, SidebarNavService $sidebar): JsonResponse
    {
        abort_unless($request->user()?->can('sidebar:manage'), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'uuid', 'exists:sidebar_nav_items,id'],
            'type' => ['required', 'string', 'in:'.implode(',', SidebarNavService::TYPES)],
            'title' => ['required', 'string', 'max:128'],
            'icon' => ['nullable', 'string', 'max:64'],
            'href' => ['nullable', 'string', 'max:512'],
            'entity_slug' => ['nullable', 'string', 'max:128'],
            'permission_key' => ['nullable', 'string', 'max:128'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string', 'max:128'],
            'permissions_match' => ['nullable', 'in:all,any'],
            'module' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
            'is_global_default' => ['nullable', 'boolean'],
            'role_default_ids' => ['nullable', 'array'],
            'role_default_ids.*' => ['integer'],
            'key' => ['nullable', 'string', 'max:128'],
        ]);

        $item = $sidebar->create($data);

        return $this->ok([
            'id' => $item->id,
            'title' => $item->title,
            'type' => $item->type,
        ], 201);
    }
}
