<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Modules\ProjectOne\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectIndexService
{
    public function paginate(int $page, int $perPage, string $search, ?string $siteId = null): LengthAwarePaginator
    {
        $query = Project::query()
            ->with([
                'site:id,site_code,name',
                'projectManager:id,name,email',
            ])
            ->orderByDesc('updated_at');

        if ($siteId !== null && $siteId !== '') {
            $query->where('site_id', $siteId);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('site', static function ($site) use ($like): void {
                        $site->where('site_code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()->map(static function (Project $project): array {
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
                    ] : null,
                    'project_manager' => $project->projectManager ? [
                        'id' => $project->projectManager->id,
                        'name' => $project->projectManager->name,
                        'email' => $project->projectManager->email,
                    ] : null,
                    'created_at' => $project->created_at?->toIso8601String(),
                    'updated_at' => $project->updated_at?->toIso8601String(),
                ];
            })->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
