<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Models\Milestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMilestoneUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, Milestone $milestone): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:manage'), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,overdue'],
        ]);

        $milestone->status = $validated['status'];
        $milestone->save();

        return $this->ok([
            'id' => $milestone->id,
            'status' => $milestone->status,
        ]);
    }
}
