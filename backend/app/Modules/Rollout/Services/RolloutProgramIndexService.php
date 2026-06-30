<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class RolloutProgramIndexService
{
    public function __construct(
        private readonly RolloutSlaAtRiskService $slaAtRisk,
    ) {}

    private const SORTABLE = [
        'created_at',
        'rollout_ref',
        'status',
        'target_rfi_working_date',
        'endorsement_date',
    ];

    /**
     * @param  array{search?: string, status?: string, mno?: string, project_type?: string, region?: string, sort?: string, sla_at_risk?: bool, view?: string}  $filters
     */
    public function paginate(int $page, int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array{search?: string, status?: string, mno?: string, project_type?: string, region?: string, sort?: string, sla_at_risk?: bool}  $filters
     * @return list<RolloutProgram>
     */
    public function allForExport(array $filters = [], int $limit = 5000): array
    {
        return $this->baseQuery(array_merge($filters, ['view' => 'full']))
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Flat list for CSV export: top-level rollouts plus sites under batch parents.
     *
     * @param  array{search?: string, status?: string, mno?: string, project_type?: string, region?: string, sort?: string, sla_at_risk?: bool}  $filters
     * @return list<RolloutProgram>
     */
    public function flattenedForExport(array $filters = [], int $limit = 5000): array
    {
        $parents = $this->baseQuery(array_merge($filters, ['view' => 'full']))
            ->with($this->exportRelations())
            ->limit($limit)
            ->get();

        $flat = [];

        foreach ($parents as $program) {
            $flat[] = $program;

            if ($program->status === 'batch') {
                foreach ($program->children as $child) {
                    $flat[] = $child;
                }
            }
        }

        return $flat;
    }

    /**
     * @return array<int|string, mixed>
     */
    /**
     * @return array<int|string, mixed>
     */
    private function listRelations(bool $summary): array
    {
        if ($summary) {
            return [
                'children' => static fn ($childQuery) => $childQuery
                    ->orderBy('rollout_ref')
                    ->select([
                        'id',
                        'parent_rollout_id',
                        'rollout_ref',
                        'search_ring_name',
                        'status',
                        'mno',
                        'project_type',
                        'region',
                        'tco_site_id',
                        'endorsement_date',
                        'tssr_approved_date',
                        'target_rfi_working_date',
                    ]),
            ];
        }

        return [
            'timelinePhases',
            'candidates',
            'children' => static fn ($childQuery) => $childQuery->orderBy('rollout_ref'),
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function exportRelations(): array
    {
        return [
            'timelinePhases',
            'candidates',
            'saqOwner',
            'pmoOwner',
            'cmePm',
            'site',
            'project',
            'parent',
            'children' => static fn ($childQuery) => $childQuery
                ->orderBy('rollout_ref')
                ->with(['timelinePhases', 'candidates', 'saqOwner', 'pmoOwner', 'cmePm', 'site', 'project', 'parent']),
        ];
    }

    /**
     * @param  array{search?: string, status?: string, mno?: string, project_type?: string, region?: string, sort?: string, sla_at_risk?: bool}  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $summary = ($filters['view'] ?? 'summary') !== 'full';

        $query = RolloutProgram::query()
            ->with($this->listRelations($summary))
            ->withCount($summary ? ['children', 'candidates', 'timelinePhases'] : ['children'])
            ->whereNull('parent_rollout_id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('rollout_ref', 'like', $like)
                    ->orWhere('search_ring_name', 'like', $like)
                    ->orWhere('tco_site_id', 'like', $like)
                    ->orWhere('endorsement_ref', 'like', $like);
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $mno = trim((string) ($filters['mno'] ?? ''));
        if ($mno !== '' && $mno !== 'all') {
            $query->where('mno', strtolower($mno));
        }

        $projectType = trim((string) ($filters['project_type'] ?? ''));
        if ($projectType !== '' && $projectType !== 'all') {
            $query->where('project_type', strtolower($projectType));
        }

        $region = trim((string) ($filters['region'] ?? ''));
        if ($region !== '' && $region !== 'all') {
            $query->where('region', strtolower($region));
        }

        if (! empty($filters['sla_at_risk'])) {
            $ids = $this->slaAtRisk->ids();
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $ids);
            }
        }

        [$sortColumn, $sortDirection] = $this->resolveSort((string) ($filters['sort'] ?? 'created_at:desc'));
        $query->orderBy($sortColumn, $sortDirection);

        return $query;
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function resolveSort(string $sort): array
    {
        $parts = explode(':', $sort);
        $column = $parts[0] ?? 'created_at';
        $direction = strtolower($parts[1] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($column, self::SORTABLE, true)) {
            $column = 'created_at';
        }

        return [$column, $direction];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(RolloutProgram $program): array
    {
        return [
            'id' => $program->id,
            'rollout_ref' => $program->rollout_ref,
            'tco_site_id' => $program->tco_site_id,
            'project_id' => $program->project_id,
            'site_id' => $program->site_id,
            'mno' => $program->mno,
            'project_type' => $program->project_type,
            'status' => $program->status,
            'region' => $program->region,
            'territory' => $program->territory,
            'search_ring_name' => $program->search_ring_name,
            'is_batch' => $program->status === 'batch',
            'child_count' => (int) $program->children_count,
            'batch_children' => $program->status === 'batch'
                ? $program->children
                    ->map(fn (RolloutProgram $child): array => $this->mapChildRow($child, (string) $program->rollout_ref))
                    ->values()
                    ->all()
                : [],
            'endorsement_date' => $program->endorsement_date?->toDateString(),
            'tssr_approved_date' => $program->tssr_approved_date?->toDateString(),
            'sla_working_days' => $program->sla_working_days,
            'target_rfi_working_date' => $program->target_rfi_working_date?->toDateString(),
            'actual_rfi_date' => $program->actual_rfi_date?->toDateString(),
            'sla_variance_working_days' => $program->sla_variance_working_days,
            'candidate_count' => $program->status === 'batch'
                ? 0
                : (int) ($program->candidates_count ?? $program->candidates()->count()),
            'phase_count' => $program->status === 'batch'
                ? 0
                : (int) ($program->timeline_phases_count ?? $program->timelinePhases()->count()),
            'cancelled_at' => $program->cancelled_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapChildRow(RolloutProgram $child, string $parentRolloutRef): array
    {
        return [
            'id' => $child->id,
            'rollout_ref' => $child->rollout_ref,
            'parent_rollout_ref' => $parentRolloutRef,
            'search_ring_name' => $child->search_ring_name,
            'status' => $child->status,
            'mno' => $child->mno,
            'project_type' => $child->project_type,
            'region' => $child->region,
            'tco_site_id' => $child->tco_site_id,
            'endorsement_date' => $child->endorsement_date?->toDateString(),
            'tssr_approved_date' => $child->tssr_approved_date?->toDateString(),
            'target_rfi_working_date' => $child->target_rfi_working_date?->toDateString(),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()->map(fn (RolloutProgram $p) => $this->mapRow($p))->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
