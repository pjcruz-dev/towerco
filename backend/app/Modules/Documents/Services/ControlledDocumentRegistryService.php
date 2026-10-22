<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentRegistryService
{
    private const SORTABLE = [
        'document_code',
        'dcf_number',
        'title',
        'document_type',
        'department',
        'current_revision',
        'status',
        'effective_date',
        'next_review_date',
        'published_at',
    ];

    public function __construct(
        private readonly ControlledDocumentAccessService $access,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     department?: string|null,
     *     status?: string|null,
     *     document_type?: string|null,
     *     review?: string|null,
     *     file?: string|null,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     * }  $filters
     * @return array{
     *     kpis: array<string, int>,
     *     facets: array{departments: list<string>, document_types: list<string>},
     *     documents: LengthAwarePaginator
     * }
     */
    public function paginate(
        TenantUser $actor,
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $department = null,
        ?string $status = null,
        ?string $documentType = null,
        ?string $sort = null,
        array $filters = [],
    ): array {
        $filters = array_merge([
            'search' => $search,
            'department' => $department,
            'document_type' => $documentType,
            'review' => null,
            'file' => null,
            'effective_from' => null,
            'effective_to' => null,
        ], $filters);
        $statusFilter = $filters['status'] ?? $status;

        $scoped = ControlledDocument::query();
        $this->access->applyRegistryScope($scoped, $actor);

        $counted = clone $scoped;
        $this->applyFilters($counted, $filters, includeStatus: false);

        $incoming = $this->openRevisionIndex();
        $list = clone $counted;
        if ($statusFilter === 'incoming') {
            $this->applyIncomingFilter($list, array_keys($incoming));
        } elseif (is_string($statusFilter) && trim($statusFilter) !== '') {
            $list->where('status', trim($statusFilter));
        }

        $list->with(['createdBy:id,name']);
        $list->withExists([
            'revisions as has_file' => static function (Builder $query): void {
                $query->whereColumn(
                    'controlled_document_revisions.revision_number',
                    'controlled_documents.current_revision',
                )
                    ->whereNotNull('controlled_document_revisions.stored_path')
                    ->where('controlled_document_revisions.stored_path', '!=', '');
            },
        ]);

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'document_code:asc'),
            self::SORTABLE,
            'document_code',
            'asc',
        );
        $list->orderBy($column, $direction);

        return [
            'kpis' => [
                ...$this->statusTotals($counted),
                'incoming' => $this->incomingCount($counted, array_keys($incoming)),
            ],
            'incoming_by_code' => $incoming,
            'facets' => $this->facets($scoped),
            'documents' => $list->paginate(perPage: $perPage, page: $page),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(ControlledDocument $document): array
    {
        $document->load([
            'createdBy:id,name',
            'revisions' => static fn ($q) => $q->orderByDesc('revision_number'),
            'revisions.approvedBy:id,name',
            'revisions.submission:id,document_no,status',
        ]);

        return $this->toDetail($document);
    }

    /**
     * @param  array{title?: string, department?: string|null}  $data
     */
    public function updateDetails(ControlledDocument $document, TenantUser $actor, array $data): ControlledDocument
    {
        $changes = [];
        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw ValidationException::withMessages([
                    'title' => [__('A title is required.')],
                ]);
            }
            if ($title !== (string) $document->title) {
                $changes['title'] = ['from' => (string) $document->title, 'to' => $title];
                $document->title = $title;
            }
        }
        if (array_key_exists('department', $data)) {
            $department = trim((string) $data['department']);
            $department = $department === '' ? null : $department;
            if ($department !== $document->department) {
                $changes['department'] = ['from' => $document->department, 'to' => $department];
                $document->department = $department;
            }
        }
        if ($changes === []) {
            return $document;
        }

        $document->save();
        $this->activity->record(
            module: 'documents',
            action: 'controlled_document.updated',
            summary: 'Updated controlled document · '.$document->document_code,
            entityType: 'controlled_document',
            entityId: (string) $document->id,
            entityLabel: (string) $document->document_code,
            actor: $actor,
            changes: WorkspaceAuditChanges::of($changes),
        );

        return $document->fresh() ?? $document;
    }

    public function markObsolete(ControlledDocument $document, TenantUser $actor): ControlledDocument
    {
        if ($document->status === ControlledDocumentStatus::OBSOLETE) {
            return $document;
        }

        $previousStatus = (string) $document->status;
        $document->status = ControlledDocumentStatus::OBSOLETE;
        $document->save();

        $this->activity->record(
            module: 'documents',
            action: 'controlled_document.obsolete',
            summary: 'Marked controlled document obsolete',
            entityType: 'controlled_document',
            entityId: (string) $document->id,
            entityLabel: $document->document_code,
            actor: $actor,
            changes: WorkspaceAuditChanges::of([
                'status' => [
                    'from' => $previousStatus,
                    'to' => ControlledDocumentStatus::OBSOLETE,
                ],
            ]),
        );

        return $document->fresh();
    }

    /**
     * @return array<string, int>
     */
    public function kpis(?TenantUser $actor = null): array
    {
        $query = ControlledDocument::query();
        if ($actor instanceof TenantUser) {
            $this->access->applyRegistryScope($query, $actor);
        }

        $total = (clone $query)->count();
        $pending = (clone $query)->where('status', ControlledDocumentStatus::PENDING)->count();
        $published = (clone $query)->where('status', ControlledDocumentStatus::PUBLISHED)->count();
        $obsolete = (clone $query)->where('status', ControlledDocumentStatus::OBSOLETE)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'published' => $published,
            'obsolete' => $obsolete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(TenantUser $actor): array
    {
        $scoped = $this->scoped($actor);
        $today = Carbon::today();
        $publishedFrom = $today->copy()->subDays(30)->startOfDay();

        $recent = (clone $scoped)
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get(['id', 'document_code', 'title', 'department', 'document_type', 'status', 'published_at']);

        $publishedScope = (clone $scoped)->where('status', ControlledDocumentStatus::PUBLISHED);

        return [
            'as_of' => $today->toDateString(),
            'published_from' => $publishedFrom->toDateString(),
            'counts' => [
                ...$this->statusTotals($scoped),
                'published_last_30_days' => (clone $scoped)
                    ->where('status', ControlledDocumentStatus::PUBLISHED)
                    ->where('published_at', '>=', $publishedFrom)
                    ->count(),
                'overdue' => $this->reviewCount($publishedScope, 'overdue'),
                'due_30' => $this->reviewCount($publishedScope, 'due_30'),
                'due_90' => $this->reviewCount($publishedScope, 'due_90'),
                'no_review' => $this->reviewCount($publishedScope, 'none'),
            ],
            'by_department' => $this->countGroups($publishedScope, 'department'),
            'by_document_type' => $this->countGroups($publishedScope, 'document_type'),
            'recent' => $recent->map(static fn (ControlledDocument $document): array => [
                'id' => (string) $document->id,
                'document_code' => $document->document_code,
                'title' => $document->title,
                'department' => $document->department,
                'document_type' => $document->document_type,
                'status' => $document->status,
                'published_at' => $document->published_at?->toIso8601String(),
            ])->values()->all(),
            'published_over_time' => $this->publishedOverTime($scoped),
        ];
    }

    /**
     * Real 14-day published counts for the register the viewer can see.
     *
     * @return list<array{key: string, label: string, value: int}>
     */
    private function publishedOverTime(Builder $scoped): array
    {
        $to = Carbon::now()->startOfDay();
        $from = $to->copy()->subDays(13);
        $counts = (clone $scoped)
            ->selectRaw('DATE(published_at) as day_key, COUNT(*) as total')
            ->where('status', ControlledDocumentStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$from, $to->copy()->endOfDay()])
            ->groupBy('day_key')
            ->pluck('total', 'day_key');

        $series = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $series[] = [
                'key' => $key,
                'label' => $day->format('M j'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(TenantUser $actor, array $filters): array
    {
        [$from, $to] = $this->reportWindow($filters);
        $filters['published_from'] = $from->toDateString();
        $filters['published_to'] = $to->toDateString();

        $scoped = $this->scoped($actor);
        $filtered = clone $scoped;
        $this->applyFilters($filtered, $filters);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'facets' => $this->facets($scoped),
            'counts' => [
                'total' => (clone $filtered)->count(),
                'published' => (clone $filtered)->where('status', ControlledDocumentStatus::PUBLISHED)->count(),
                'obsolete' => (clone $filtered)->where('status', ControlledDocumentStatus::OBSOLETE)->count(),
            ],
            'publications' => $this->publicationSeries($filtered, $from, $to),
            'by_department' => $this->countGroups($filtered, 'department'),
            'by_document_type' => $this->countGroups($filtered, 'document_type'),
            'review' => $this->reviewHorizon($filtered),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(TenantUser $actor, array $filters): string
    {
        [$from, $to] = $this->reportWindow($filters);
        $filters['published_from'] = $from->toDateString();
        $filters['published_to'] = $to->toDateString();

        $query = $this->scoped($actor);
        $this->applyFilters($query, $filters);
        $query->withExists([
            'revisions as has_file' => static function (Builder $inner): void {
                $inner->whereColumn(
                    'controlled_document_revisions.revision_number',
                    'controlled_documents.current_revision',
                )
                    ->whereNotNull('controlled_document_revisions.stored_path')
                    ->where('controlled_document_revisions.stored_path', '!=', '');
            },
        ]);

        $documents = $query->orderBy('document_code')->limit(5000)->get();
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'export' => [__('Could not build the export.')],
            ]);
        }

        fputcsv($handle, [
            'document_code',
            'dcf_number',
            'title',
            'document_type',
            'department',
            'revision_number',
            'status',
            'effective_date',
            'next_review_date',
            'published_at',
            'has_file',
        ]);

        foreach ($documents as $document) {
            fputcsv($handle, [
                $document->document_code,
                $document->dcf_number,
                $document->title,
                $document->document_type,
                $document->department,
                (string) $document->current_revision,
                $document->status,
                $document->effective_date?->toDateString(),
                $document->next_review_date?->toDateString(),
                $document->published_at?->toDateString(),
                $document->getAttribute('has_file') ? 'yes' : 'no',
            ]);
        }

        rewind($handle);
        $csv = "\xEF\xBB\xBF".stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return Builder<ControlledDocument>
     */
    private function scoped(TenantUser $actor): Builder
    {
        $query = ControlledDocument::query();
        $this->access->applyRegistryScope($query, $actor);

        return $query;
    }

    /**
     * @param  Builder<ControlledDocument>  $scoped
     */
    private function reviewCount(Builder $scoped, string $review): int
    {
        $query = clone $scoped;
        $this->applyReviewFilter($query, $review);

        return $query->count();
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @param  array{
     *     search?: string|null,
     *     department?: string|null,
     *     status?: string|null,
     *     document_type?: string|null,
     *     review?: string|null,
     *     file?: string|null,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     *     published_from?: string|null,
     *     published_to?: string|null,
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters, bool $includeStatus = true): void
    {
        $search = $filters['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $needle = '%'.strtolower(trim($search)).'%';
            $query->where(static function (Builder $inner) use ($needle): void {
                $inner->whereRaw('LOWER(document_code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(dcf_number) LIKE ?', [$needle]);
            });
        }

        $department = $filters['department'] ?? null;
        if (is_string($department) && trim($department) !== '') {
            $query->where('department', trim($department));
        }

        $documentType = $filters['document_type'] ?? null;
        if (is_string($documentType) && trim($documentType) !== '') {
            $query->where('document_type', trim($documentType));
        }

        if ($includeStatus) {
            $status = $filters['status'] ?? null;
            if (is_string($status) && trim($status) !== '') {
                $query->where('status', trim($status));
            }
        }

        $this->applyReviewFilter($query, $filters['review'] ?? null);
        $this->applyFileFilter($query, $filters['file'] ?? null);

        $effectiveFrom = $filters['effective_from'] ?? null;
        if (is_string($effectiveFrom) && $effectiveFrom !== '') {
            $query->whereDate('effective_date', '>=', $effectiveFrom);
        }

        $effectiveTo = $filters['effective_to'] ?? null;
        if (is_string($effectiveTo) && $effectiveTo !== '') {
            $query->whereDate('effective_date', '<=', $effectiveTo);
        }

        $publishedFrom = $filters['published_from'] ?? null;
        if (is_string($publishedFrom) && $publishedFrom !== '') {
            $query->where('published_at', '>=', Carbon::parse($publishedFrom)->startOfDay());
        }

        $publishedTo = $filters['published_to'] ?? null;
        if (is_string($publishedTo) && $publishedTo !== '') {
            $query->where('published_at', '<=', Carbon::parse($publishedTo)->endOfDay());
        }
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     */
    private function applyReviewFilter(Builder $query, mixed $review): void
    {
        if (! is_string($review) || $review === '') {
            return;
        }

        $today = Carbon::today()->toDateString();

        if ($review === 'none') {
            $query->whereNull('next_review_date');

            return;
        }

        if ($review === 'overdue') {
            $query->whereNotNull('next_review_date')
                ->whereDate('next_review_date', '<', $today);

            return;
        }

        $horizon = match ($review) {
            'due_30' => Carbon::today()->addDays(30)->toDateString(),
            'due_90' => Carbon::today()->addDays(90)->toDateString(),
            default => null,
        };

        if ($horizon === null) {
            return;
        }

        $query->whereDate('next_review_date', '>=', $today)
            ->whereDate('next_review_date', '<=', $horizon);
    }

    /**
     * Published rows with a revision still waiting in E-Forms, keyed by document code.
     *
     * @return array<string, array{submission_id: string, document_no: string, revision_number: int}>
     */
    private function openRevisionIndex(): array
    {
        if (app()->runningUnitTests()) {
            return $this->queryOpenRevisionIndex();
        }

        $tenantId = (string) (tenant('id') ?? 'unknown');

        /** @var array<string, array{submission_id: string, document_no: string, revision_number: int}> $index */
        $index = TenantScopedCache::remember(
            "documents:controlled:open-revisions:{$tenantId}",
            30,
            fn (): array => $this->queryOpenRevisionIndex(),
        );

        return $index;
    }

    /**
     * @return array<string, array{submission_id: string, document_no: string, revision_number: int}>
     */
    private function queryOpenRevisionIndex(): array
    {
        $index = [];
        $submissions = EApprovalSubmission::query()
            ->where('status', EApprovalSubmissionStatus::PENDING)
            ->where('document_no', 'like', '%-R%')
            ->get(['id', 'document_no']);

        foreach ($submissions as $submission) {
            if (preg_match('/^(.+)-R(\d+)$/', (string) $submission->document_no, $matches) !== 1) {
                continue;
            }
            $code = $matches[1];
            $revision = (int) $matches[2];
            $current = $index[$code] ?? null;
            if (is_array($current) && $current['revision_number'] <= $revision) {
                continue;
            }
            $index[$code] = [
                'submission_id' => (string) $submission->id,
                'document_no' => (string) $submission->document_no,
                'revision_number' => $revision,
            ];
        }

        return $index;
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @return array{total: int, pending: int, published: int, obsolete: int}
     */
    private function statusTotals(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($rows[ControlledDocumentStatus::PENDING] ?? 0);
        $published = (int) ($rows[ControlledDocumentStatus::PUBLISHED] ?? 0);
        $obsolete = (int) ($rows[ControlledDocumentStatus::OBSOLETE] ?? 0);

        return [
            'total' => (int) $rows->sum(),
            'pending' => $pending,
            'published' => $published,
            'obsolete' => $obsolete,
        ];
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @param  list<string>  $codes
     */
    private function applyIncomingFilter(Builder $query, array $codes): void
    {
        if ($codes === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', ControlledDocumentStatus::PUBLISHED)
            ->whereIn('document_code', $codes);
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @param  list<string>  $codes
     */
    private function incomingCount(Builder $query, array $codes): int
    {
        if ($codes === []) {
            return 0;
        }

        return (clone $query)
            ->where('status', ControlledDocumentStatus::PUBLISHED)
            ->whereIn('document_code', $codes)
            ->count();
    }

    /**
     * @param  array{submission_id: string, document_no: string, revision_number: int}|null  $incoming
     * @return array{incoming_revision: int|null, incoming_submission_id: string|null, incoming_document_no: string|null}
     */
    private function incomingFields(?array $incoming): array
    {
        return [
            'incoming_revision' => $incoming['revision_number'] ?? null,
            'incoming_submission_id' => $incoming['submission_id'] ?? null,
            'incoming_document_no' => $incoming['document_no'] ?? null,
        ];
    }

    private function applyFileFilter(Builder $query, mixed $file): void
    {
        if ($file !== 'has' && $file !== 'missing') {
            return;
        }

        $method = $file === 'has' ? 'whereExists' : 'whereNotExists';
        $query->{$method}(function ($inner): void {
            $inner->selectRaw('1')
                ->from('controlled_document_revisions')
                ->whereColumn('controlled_document_revisions.controlled_document_id', 'controlled_documents.id')
                ->whereColumn('controlled_document_revisions.revision_number', 'controlled_documents.current_revision')
                ->whereNotNull('controlled_document_revisions.stored_path')
                ->where('controlled_document_revisions.stored_path', '!=', '');
        });
    }

    /**
     * @param  Builder<ControlledDocument>  $scoped
     * @return array{departments: list<string>, document_types: list<string>}
     */
    private function facets(Builder $scoped): array
    {
        return [
            'departments' => $this->distinctColumn($scoped, 'department'),
            'document_types' => $this->distinctColumn($scoped, 'document_type'),
        ];
    }

    /**
     * @param  Builder<ControlledDocument>  $scoped
     * @return list<string>
     */
    private function distinctColumn(Builder $scoped, string $column): array
    {
        return (clone $scoped)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(ControlledDocument $document): array
    {
        return [
            'id' => (string) $document->id,
            'document_code' => $document->document_code,
            'dcf_number' => $document->dcf_number,
            'e_approval_form_id' => $document->e_approval_form_id,
            'title' => $document->title,
            'document_type' => $document->document_type,
            'department' => $document->department,
            'current_revision' => (int) $document->current_revision,
            'status' => $document->status,
            'effective_date' => $document->effective_date?->toDateString(),
            'next_review_date' => $document->next_review_date?->toDateString(),
            'published_at' => $document->published_at?->toIso8601String(),
            'created_by_name' => $document->createdBy?->name,
            ...$this->incomingFields($this->openRevisionIndex()[(string) $document->document_code] ?? null),
            'revisions' => $document->revisions->map(static fn (ControlledDocumentRevision $revision): array => [
                'id' => (string) $revision->id,
                'revision_number' => (int) $revision->revision_number,
                'dcf_number' => $revision->dcf_number,
                'status' => $revision->status,
                'change_summary' => $revision->change_summary,
                'effective_date' => $revision->effective_date?->toDateString(),
                'approved_at' => $revision->approved_at?->toIso8601String(),
                'approved_by_name' => $revision->approvedBy?->name,
                'has_file' => $revision->stored_path !== null && $revision->stored_path !== '',
                'original_filename' => $revision->original_filename,
                'e_approval_submission_id' => $revision->e_approval_submission_id,
                'e_approval_document_no' => $revision->submission?->document_no,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function reportWindow(array $filters): array
    {
        $fromRaw = $filters['published_from'] ?? null;
        $toRaw = $filters['published_to'] ?? null;
        if (is_string($fromRaw) && $fromRaw !== '' && is_string($toRaw) && $toRaw !== '') {
            $from = Carbon::parse($fromRaw)->startOfDay();
            $to = Carbon::parse($toRaw)->endOfDay();
        } else {
            $period = (int) ($filters['period'] ?? 90);
            if (! in_array($period, [30, 90, 365], true)) {
                $period = 90;
            }
            $to = Carbon::today()->endOfDay();
            $from = Carbon::today()->subDays($period)->startOfDay();
        }

        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'period' => [__('Choose a period of 366 days or less.')],
            ]);
        }

        return [$from, $to];
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @return list<array{key: string, label: string, value: int}>
     */
    private function countGroups(Builder $query, string $column): array
    {
        $rows = (clone $query)
            ->selectRaw($column.' as bucket, COUNT(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->get();

        $items = [];
        $other = 0;
        foreach ($rows as $index => $row) {
            $label = trim((string) $row->getAttribute('bucket'));
            $value = (int) $row->getAttribute('total');
            if ($label === '') {
                $label = 'Unassigned';
            }
            if ($index >= 8) {
                $other += $value;

                continue;
            }
            $items[] = ['key' => $label === 'Unassigned' ? '' : $label, 'label' => $label, 'value' => $value];
        }
        if ($other > 0) {
            $items[] = ['key' => '__other__', 'label' => 'Other', 'value' => $other];
        }

        return $items;
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @return list<array{key: string, label: string, value: int}>
     */
    private function reviewHorizon(Builder $query): array
    {
        $today = Carbon::today()->toDateString();
        $in30 = Carbon::today()->addDays(30)->toDateString();
        $in90 = Carbon::today()->addDays(90)->toDateString();

        return [
            [
                'key' => 'overdue',
                'label' => 'Overdue',
                'value' => (clone $query)->whereNotNull('next_review_date')->whereDate('next_review_date', '<', $today)->count(),
            ],
            [
                'key' => 'due_30',
                'label' => 'Due in 30 days',
                'value' => (clone $query)->whereDate('next_review_date', '>=', $today)->whereDate('next_review_date', '<=', $in30)->count(),
            ],
            [
                'key' => 'due_31_90',
                'label' => 'Due in 31–90 days',
                'value' => (clone $query)->whereDate('next_review_date', '>', $in30)->whereDate('next_review_date', '<=', $in90)->count(),
            ],
            [
                'key' => 'none',
                'label' => 'No review date',
                'value' => (clone $query)->whereNull('next_review_date')->count(),
            ],
        ];
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @return list<array{key: string, label: string, value: int}>
     */
    private function publicationSeries(Builder $query, Carbon $from, Carbon $to): array
    {
        $span = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay());
        $grain = $span <= 45 ? 'day' : ($span <= 120 ? 'week' : 'month');
        $counts = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        if ($grain === 'week') {
            $cursor = $cursor->startOfWeek(Carbon::MONDAY);
        } elseif ($grain === 'month') {
            $cursor = $cursor->startOfMonth();
        }

        while ($cursor->lte($end)) {
            $key = $grain === 'month' ? $cursor->format('Y-m') : $cursor->toDateString();
            $counts[$key] = 0;
            if ($grain === 'day') {
                $cursor->addDay();
            } elseif ($grain === 'week') {
                $cursor->addWeek();
            } else {
                $cursor->addMonth();
            }
        }

        foreach ((clone $query)->whereNotNull('published_at')->pluck('published_at') as $value) {
            $date = Carbon::parse($value);
            $key = match ($grain) {
                'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            };
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        $points = [];
        foreach ($counts as $key => $value) {
            $label = $grain === 'month'
                ? Carbon::parse($key.'-01')->format('M Y')
                : Carbon::parse($key)->format('M j');
            $points[] = ['key' => $key, 'label' => $label, 'value' => $value];
        }

        return $points;
    }

    public function findRevisionOrFail(ControlledDocument $document, string $revisionId): ControlledDocumentRevision
    {
        $revision = ControlledDocumentRevision::query()
            ->where('controlled_document_id', $document->id)
            ->whereKey($revisionId)
            ->first();

        if ($revision === null) {
            throw ValidationException::withMessages([
                'revision' => [__('Revision not found for this document.')],
            ]);
        }

        return $revision;
    }
}
