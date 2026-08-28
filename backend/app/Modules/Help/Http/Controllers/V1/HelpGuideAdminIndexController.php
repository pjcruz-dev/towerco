<?php

declare(strict_types=1);

namespace App\Modules\Help\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Help\Services\HelpGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HelpGuideAdminIndexController extends AbstractApiController
{
    public function __invoke(Request $request, HelpGuideService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $module = $request->query('module');
        $moduleKey = is_string($module) && $module !== '' ? $module : null;

        $rows = $service->listForAdmin($moduleKey)
            ->map(fn ($guide) => $service->asListRow($guide))
            ->values()
            ->all();

        return $this->ok($rows);
    }
}
