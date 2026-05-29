<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\ProjectOne\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectShowController extends AbstractApiController
{
    public function __invoke(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:view'), 403);

        return $this->ok($service->showPayload($project));
    }
}
