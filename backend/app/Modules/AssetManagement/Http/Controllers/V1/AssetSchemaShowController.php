<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetSchemaShowController extends AbstractApiController
{
    public function __invoke(Request $request, AssetSchemaService $schema): JsonResponse
    {
        abort_unless($request->user()?->can('assets:view') || $request->user()?->can('assets:fields:manage'), 403);

        return $this->ok($schema->show());
    }
}
