<?php

declare(strict_types=1);

namespace App\Modules\Help\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Help\Services\HelpGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HelpGuideIndexController extends AbstractApiController
{
    public function __invoke(Request $request, HelpGuideService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $module = $request->query('module');
        $role = $request->query('role');
        $moduleKey = is_string($module) && $module !== '' ? $module : null;
        $roleKey = is_string($role) && $role !== '' ? $role : null;

        $rows = $service->listPublished($moduleKey, $roleKey)
            ->map(fn ($guide) => $service->asListRow($guide))
            ->values()
            ->all();

        return $this->ok($rows);
    }
}
