<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetIndexController extends AbstractApiController
{
    public function __invoke(Request $request, AssetQueryService $query): JsonResponse
    {
        $user = AssetActor::from($request);
        $full = $user->can('assets:view');
        abort_unless($full || $user->can('assets:self:view'), 403);
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:180'],
            'status' => ['sometimes', 'string', 'max:80'],
            'asset_type' => ['sometimes', 'string', 'max:32'],
            'manufacturer' => ['sometimes', 'string', 'max:180'],
            'department' => ['sometimes', 'string', 'max:180'],
            'lifecycle' => ['sometimes', 'string', 'max:80'],
            'attention' => ['sometimes', 'string', 'in:accountability'],
            'repair' => ['sometimes', 'string', 'in:PENDING_VENDOR'],
            'sort' => ['sometimes', 'string', 'max:80'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $page = $query->paginate($filters, $full ? null : (string) $user->id);

        return $this->okWithMeta(
            collect($page->items())->map(fn ($asset) => $query->present($asset))->values()->all(),
            [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        );
    }
}
