<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\Rollout\Models\TenantRolloutFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectApprovalIndexService
{
    public function __construct(
        private readonly ProjectApprovalAttachmentService $attachments,
    ) {}

    public function paginate(int $page, int $perPage, string $search, string $status): LengthAwarePaginator
    {
        $query = ProjectApproval::query()
            ->with(['project:id,name', 'rolloutProgram:id,rollout_ref', 'resolvedBy:id,name'])
            ->orderByDesc('submitted_at');

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
