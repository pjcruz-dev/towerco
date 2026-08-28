<?php

declare(strict_types=1);

namespace App\Modules\Help\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Help\Services\HelpGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HelpGuideShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $slug, HelpGuideService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $guide = $service->findPublishedBySlugOrFail($slug);

        return $this->ok($service->asDetail($guide));
    }
}
