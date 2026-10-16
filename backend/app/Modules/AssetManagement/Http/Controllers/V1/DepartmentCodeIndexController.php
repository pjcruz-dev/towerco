<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\DepartmentCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DepartmentCodeIndexController extends AbstractApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('assets:view') || $request->user()?->can('assets:settings:manage'), 403);

        return $this->ok(DepartmentCode::query()->orderBy('department_name')->get()->toArray());
    }
}
