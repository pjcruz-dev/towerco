<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\Rollout\Models\TenantRolloutFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectApprovalIndexService
{
    private const SORTABLE = [
        'status',
        'approval_type',
        'title',
        'requester',
        'submitted_at',
        'resolved_at',
    ];

    public function __construct(
        private readonly ProjectApprovalAttachmentService $attachments,
    ) {}

    public function paginate(int $page, int $perPage, string $search, string $status, ?string $sort = null): LengthAwarePaginator
    {
        $query = ProjectApproval::query()
            ->with(['project:id,name', 'rolloutProgram:id,rollout_ref', 'resolvedBy:id,name']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('requester', 'like', $like)
                    ->orWhere('approval_type', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'submitted_at:desc'),
            self::SORTABLE,
            'submitted_at',
            'desc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        $attachmentIndex = $this->attachmentIndexFor($paginator->getCollection());

        return [
            'data' => $paginator->getCollection()->map(function (ProjectApproval $approval) use ($attachmentIndex): array {
                $row = $approval->toListRow();
                $row['attachments'] = $this->attachments->enrichFromIndex(
                    $approval->attachment_file_ids,
                    $attachmentIndex,
                );

                return $row;
            })->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @param  Collection<int, ProjectApproval>  $approvals
     * @return Collection<string, TenantRolloutFile>
     */
    private function attachmentIndexFor(Collection $approvals): Collection
    {
        $fileIds = $approvals
            ->flatMap(static fn (ProjectApproval $approval): array => $approval->attachment_file_ids ?? [])
            ->unique()
            ->values()
            ->all();

        if ($fileIds === []) {
            return collect();
        }

        return TenantRolloutFile::query()
            ->whereIn('id', $fileIds)
            ->get()
            ->keyBy('id');
    }
}
