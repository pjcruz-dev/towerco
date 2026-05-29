<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\ProjectOne\Services\ProjectApprovalAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectApprovalStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProjectApprovalAttachmentService $attachments,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:manage'), 403);

        $validated = $request->validate([
            'approval_type' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'requester' => ['required', 'string', 'max:255'],
            'sla_risk' => ['required', 'in:low,medium,high'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'rollout_program_id' => ['nullable', 'uuid', 'exists:rollout_programs,id'],
            'attachment_file_ids' => ['sometimes', 'nullable', 'array'],
            'attachment_file_ids.*' => ['uuid'],
        ]);

        $rolloutId = $validated['rollout_program_id'] ?? null;
        $fileIds = $attachments->normalize($validated['attachment_file_ids'] ?? null, $rolloutId);

        $approval = ProjectApproval::query()->create([
            'project_id' => $validated['project_id'] ?? null,
            'rollout_program_id' => $rolloutId,
            'approval_type' => $validated['approval_type'],
            'title' => $validated['title'],
            'requester' => $validated['requester'],
            'sla_risk' => $validated['sla_risk'],
            'attachment_file_ids' => $fileIds,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $approval->load(['project:id,name', 'rolloutProgram:id,rollout_ref']);

        $row = $approval->toListRow();
        $row['attachments'] = $attachments->enrich($approval->attachment_file_ids);

        return $this->ok($row, 201);
    }
}
