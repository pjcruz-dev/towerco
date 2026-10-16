<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetDirectoryController extends AbstractApiController
{
    public function __invoke(Request $request, AssetQueryService $query): JsonResponse
    {
        abort_unless(
            $request->user()?->can('assets:view')
            || $request->user()?->can('assets:assign')
            || $request->user()?->can('assets:offboard'),
            403,
        );
        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
        ]);

        return $this->ok($query->searchPeople((string) ($data['q'] ?? '')));
    }
}
