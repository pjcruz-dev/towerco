<?php

declare(strict_types=1);

namespace App\Modules\Help\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Help\Services\HelpGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HelpGuideAdminShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $slug, HelpGuideService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $guide = $service->findBySlugOrFail($slug);

        return $this->ok($service->asDetail($guide));
    }
}
