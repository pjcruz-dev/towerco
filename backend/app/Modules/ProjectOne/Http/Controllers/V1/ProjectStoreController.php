<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectStoreController extends AbstractApiController
{
    public function __invoke(Request $request, ProjectService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'site_id' => ['sometimes', 'nullable', 'uuid', 'exists:sites,id'],
            'project_manager_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project = $service->create($data);

        return $this->created($service->showPayload($project));
    }
}
