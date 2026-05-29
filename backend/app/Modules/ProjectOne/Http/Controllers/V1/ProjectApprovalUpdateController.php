<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Models\ProjectApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectApprovalUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, ProjectApproval $approval): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:manage'), 403);

        if ($approval->status !== 'pending') {
            return response()->json([
                'message' => __('This approval is no longer pending.'),
            ], 409);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $approval->fill([
            'status' => $validated['status'],
            'resolution_notes' => $validated['resolution_notes'] ?? null,
            'resolved_at' => now(),
            'resolved_by_id' => $request->user()->id,
        ]);
        $approval->save();

        return $this->ok([
            'id' => $approval->id,
            'status' => $approval->status,
        ]);
    }
}
