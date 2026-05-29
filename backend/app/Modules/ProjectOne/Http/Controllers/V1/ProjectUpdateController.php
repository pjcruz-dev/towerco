<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\ProjectOne\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'site_id' => ['sometimes', 'nullable', 'uuid', 'exists:sites,id'],
            'project_manager_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $updated = $service->update($project, $data);

        return $this->ok($service->showPayload($updated));
    }
}
