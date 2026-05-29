<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Modules\ProjectOne\Models\Milestone;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

final class ProjectService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): Project
    {
        /** @var Project $project */
        $project = Project::query()->create([
            'name' => (string) $input['name'],
            'site_id' => $input['site_id'] ?? null,
            'project_manager_id' => $input['project_manager_id'] ?? null,
            'status' => (string) ($input['status'] ?? 'planning'),
            'start_date' => $input['start_date'] ?? null,
            'end_date' => $input['end_date'] ?? null,
        ]);

        return $project->fresh(['site', 'projectManager']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Project $project, array $input): Project
    {
        $allowed = ['name', 'site_id', 'project_manager_id', 'status', 'start_date', 'end_date'];
        $changes = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $input)) {
                $project->{$field} = $input[$field];
                $changes[$field] = $input[$field];
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'project' => [__('No editable fields were provided.')],
            ]);
        }

        $project->save();

        return $project->fresh(['site', 'projectManager']);
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Project $project): array
    {
        $project->load([
            'site:id,site_code,name,status',
            'projectManager:id,name,email',
            'milestones' => static fn ($q) => $q->orderBy('order_index'),
            'approvals' => static fn ($q) => $q->orderByDesc('submitted_at')->limit(10),
            'rollouts' => static fn ($q) => $q
                ->whereNull('parent_rollout_id')
                ->orderByDesc('created_at'),
        ]);

        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'site' => $project->site ? [
                'id' => $project->site->id,
                'site_code' => $project->site->site_code,
                'name' => $project->site->name,
                'status' => $project->site->status,
            ] : null,
            'project_manager' => $project->projectManager ? [
                'id' => $project->projectManager->id,
                'name' => $project->projectManager->name,
                'email' => $project->projectManager->email,
            ] : null,
            'milestones' => $project->milestones->map(static fn (Milestone $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'due_date' => $m->due_date?->toDateString(),
                'status' => $m->status,
                'order_index' => $m->order_index,
            ])->values()->all(),
            'approvals' => $project->approvals->map(static fn (ProjectApproval $a) => $a->toDashboardApproval())->values()->all(),
            'rollouts' => $project->rollouts->map(static fn (RolloutProgram $r) => [
                'id' => $r->id,
                'rollout_ref' => $r->rollout_ref,
                'status' => $r->status,
                'mno' => $r->mno,
                'project_type' => $r->project_type,
                'search_ring_name' => $r->search_ring_name,
                'tco_site_id' => $r->tco_site_id,
                'target_rfi_working_date' => $r->target_rfi_working_date?->toDateString(),
            ])->values()->all(),
            'rollout_count' => $project->rollouts->count(),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }
}
