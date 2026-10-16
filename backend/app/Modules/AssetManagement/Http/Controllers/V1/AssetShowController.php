<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetQueryService $query): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('assets:view') || $user?->can('assets:self:view'), 403);
        $record = $registry->findByTagOrId($asset);
        if (! $user?->can('assets:view') && (string) $record->assigned_user_id !== (string) $user?->getAuthIdentifier()) {
            abort(404);
        }

        return $this->ok($query->present($record));
    }
}
