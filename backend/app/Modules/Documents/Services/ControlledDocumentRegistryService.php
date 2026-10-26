<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\ControlledDocumentMasterLabel;
use App\Modules\Documents\Support\ControlledDocumentRevisionStatus;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalReviewStep;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantScopedCache;
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
        private readonly ControlledDocumentMasterLabel $labels,
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
        $incomingCodes = array_keys($incoming);
        $list = clone $counted;
        if ($statusFilter === 'incoming') {
            $this->applyIncomingFilter($list, $incomingCodes);
        } elseif ($statusFilter === ControlledDocumentStatus::PENDING) {
            $list->where(function (Builder $inner) use ($incomingCodes): void {
                $inner->where('status', ControlledDocumentStatus::PENDING);
                if ($incomingCodes !== []) {
                    $inner->orWhere(function (Builder $published) use ($incomingCodes): void {
                        $published->where('status', ControlledDocumentStatus::PUBLISHED)
                            ->whereIn('document_code', $incomingCodes);
                    });
                }
            });
        } elseif (is_string($statusFilter) && trim($statusFilter) === ControlledDocumentStatus::OBSOLETE) {
            $this->applyObsoleteDocumentScope($list);
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

        $totals = $this->statusTotals($counted);
        $incomingCount = $this->incomingCount($counted, $incomingCodes);
        $totals['pending'] += $incomingCount;

        return [
            'kpis' => [
                ...$totals,
                'incoming' => $incomingCount,
            ],
            'incoming_by_code' => $incoming,
            'aging_by_code' => $this->agingByDocumentCode($scoped),
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
        $obsolete = $this->obsoleteDocumentCount($query);

        return [
            'total' => $total,
            'pending' => $pending,
            'published' => $published,
            'obsolete' => $obsolete,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(TenantUser $actor, array $filters = []): array
    {
        $scoped = $this->scoped($actor);
        $filtered = clone $scoped;
        $this->applyFilters($filtered, $filters);
        $today = Carbon::today();
        $publishedFrom = $today->copy()->subDays(30)->startOfDay();

        $recent = (clone $filtered)
            ->where('status', ControlledDocumentStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get(['id', 'document_code', 'title', 'department', 'document_type', 'status', 'current_revision', 'effective_date', 'published_at']);

        $publishedScope = (clone $filtered)->where('status', ControlledDocumentStatus::PUBLISHED);
        $incomingIndex = $this->openRevisionIndex();
        $incomingCodes = array_keys($incomingIndex);
        $incoming = $this->incomingCount($filtered, $incomingCodes);
        $versioned = (clone $filtered)->where('current_revision', '>', 0)->count();
        $ongoing = (clone $publishedScope)
            ->whereNotNull('next_review_date')
            ->whereDate('next_review_date', '<=', $today->copy()->addDays(90))
            ->count();

        return [
            'as_of' => $today->toDateString(),
            'published_from' => $publishedFrom->toDateString(),
            'counts' => [
                ...$this->statusTotals($filtered),
                'published_last_30_days' => (clone $filtered)
                    ->where('status', ControlledDocumentStatus::PUBLISHED)
                    ->where('published_at', '>=', $publishedFrom)
                    ->count(),
                'overdue' => $this->reviewCount($publishedScope, 'overdue'),
                'due_30' => $this->reviewCount($publishedScope, 'due_30'),
                'due_90' => $this->reviewCount($publishedScope, 'due_90'),
                'no_review' => $this->reviewCount($publishedScope, 'none'),
                'incoming' => $incoming,
                'versioned' => $versioned,
                'ongoing' => $ongoing,
            ],
            'by_department' => $this->countGroups($publishedScope, 'department'),
            'by_document_type' => $this->countGroups($publishedScope, 'document_type'),
            'departments' => $this->departmentBreakdown($filtered, $incomingCodes),
            'facets' => $this->facets($scoped),
            'mix' => $this->registerMix($filtered, $incomingCodes),
            'aging' => $this->agingRowsFrom($agingGrouped = $this->agingByDocumentCode($filtered)),
            'aging_summary' => $this->agingSummary($agingGrouped),
            'recent' => $recent->map(static fn (ControlledDocument $document): array => [
                'id' => (string) $document->id,
                'document_code' => $document->document_code,
                'title' => $document->title,
                'department' => $document->department,
                'document_type' => $document->document_type,
                'status' => $document->status,
                'current_revision' => (int) $document->current_revision,
                'effective_date' => $document->effective_date?->toDateString(),
                'published_at' => $document->published_at?->toIso8601String(),
            ])->values()->all(),
            'published_over_time' => $this->publishedOverTime($filtered),
        ];
    }

    /**
     * File rows for the Files page: obsolete revisions, the live approved file, and pending revisions.
     *
     * @return array{
     *     counts: array{obsolete: int, approved: int, pending: int},
     *     obsolete: list<array<string, mixed>>,
     *     approved: list<array<string, mixed>>,
     *     pending: list<array<string, mixed>>
     * }
     */
    public function obsoleteBoard(TenantUser $actor): array
    {
        $documents = $this->scoped($actor)
            ->get(['id', 'document_code', 'title', 'department', 'document_type', 'status', 'current_revision']);
        $byId = $documents->keyBy(static fn (ControlledDocument $document): string => (string) $document->id);

        $revisions = $byId->isEmpty()
            ? collect()
            : ControlledDocumentRevision::query()
                ->whereIn('controlled_document_id', $byId->keys()->all())
                ->orderByDesc('approved_at')
                ->orderByDesc('revision_number')
                ->get();

        $obsolete = [];
        $approved = [];
        $pending = [];
        $documentsWithRevisions = [];

        foreach ($revisions as $revision) {
            $document = $byId->get((string) $revision->controlled_document_id);
            if (! $document instanceof ControlledDocument) {
                continue;
            }

            $documentsWithRevisions[(string) $document->id] = true;

            if ((string) $revision->status === ControlledDocumentRevisionStatus::PENDING) {
                $row = $this->obsoleteBoardRow($document, $revision);
                $row['standing'] = 'pending';
                $pending[] = $row;

                continue;
            }

            if ($this->approvedObsoleteFile($document, $revision)) {
                $row = $this->obsoleteBoardRow($document, $revision);
                $row['standing'] = 'obsolete';
                $obsolete[] = $row;

                continue;
            }

            if ($this->liveApprovedFile($document, $revision)) {
                $row = $this->obsoleteBoardRow($document, $revision);
                $row['standing'] = 'current';
                $approved[] = $row;
            }
        }

        foreach ($documents as $document) {
            if ((string) $document->status !== ControlledDocumentStatus::PENDING) {
                continue;
            }
            if (isset($documentsWithRevisions[(string) $document->id])) {
                continue;
            }

            $row = $this->obsoleteBoardRow($document, null);
            $row['standing'] = 'pending';
            $pending[] = $row;
        }

        $byApproval = static function (array $left, array $right): int {
            return strcmp((string) ($right['approved_at'] ?? ''), (string) ($left['approved_at'] ?? ''));
        };
        $missingFirst = static function (array $left, array $right) use ($byApproval): int {
            $missing = ((int) $left['has_file']) <=> ((int) $right['has_file']);

            return $missing !== 0 ? $missing : $byApproval($left, $right);
        };

        usort($obsolete, $missingFirst);
        usort($pending, $missingFirst);
        usort($approved, $byApproval);

        return [
            'counts' => [
                'obsolete' => count($obsolete),
                'approved' => count($approved),
                'pending' => count($pending),
            ],
            'obsolete' => $obsolete,
            'approved' => $approved,
            'pending' => $pending,
        ];
    }

    private function approvedObsoleteFile(ControlledDocument $document, ControlledDocumentRevision $revision): bool
    {
        $wasApproved = $revision->approved_at !== null
            || in_array((string) $revision->status, [
                ControlledDocumentRevisionStatus::PUBLISHED,
                ControlledDocumentRevisionStatus::SUPERSEDED,
            ], true);
        if (! $wasApproved) {
            return false;
        }

        $live = (string) $document->status === ControlledDocumentStatus::PUBLISHED
            && (int) $revision->revision_number === (int) $document->current_revision
            && (string) $revision->status === ControlledDocumentRevisionStatus::PUBLISHED;

        return ! $live;
    }

    private function liveApprovedFile(ControlledDocument $document, ControlledDocumentRevision $revision): bool
    {
        $live = (string) $document->status === ControlledDocumentStatus::PUBLISHED
            && (int) $revision->revision_number === (int) $document->current_revision
            && (string) $revision->status === ControlledDocumentRevisionStatus::PUBLISHED;
        if (! $live) {
            return false;
        }

        return is_string($revision->stored_path) && $revision->stored_path !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function obsoleteBoardRow(ControlledDocument $document, ?ControlledDocumentRevision $revision): array
    {
        $hasFile = $revision !== null
            && is_string($revision->stored_path)
            && $revision->stored_path !== '';
        $current = $revision !== null
            && (string) $document->status === ControlledDocumentStatus::PUBLISHED
            && (int) $revision->revision_number === (int) $document->current_revision
            && (string) $revision->status === ControlledDocumentRevisionStatus::PUBLISHED;

        return [
            'document_id' => (string) $document->id,
            'revision_id' => $revision !== null ? (string) $revision->id : null,
            'document_code' => (string) $document->document_code,
            'title' => (string) $document->title,
            'department' => $document->department,
            'document_type' => $document->document_type,
            'revision_number' => $revision !== null ? (int) $revision->revision_number : null,
            'standing' => $current ? 'current' : 'obsolete',
            'effective_date' => ($revision?->effective_date ?? $document->effective_date)?->toDateString(),
            'approved_at' => $revision?->approved_at?->toIso8601String(),
            'filename' => $hasFile ? (string) $revision->original_filename : null,
            'has_file' => $hasFile,
        ];
    }

    /**
     * Real 30-day published counts for the register the viewer can see.
     *
     * @return list<array{key: string, label: string, value: int}>
     */
    private function publishedOverTime(Builder $scoped): array
    {
        $to = Carbon::now()->startOfDay();
        $from = $to->copy()->subDays(29);
        $counts = (clone $scoped)
            ->selectRaw('DATE(published_at) as day_key, COUNT(*) as total')
            ->where('status', ControlledDocumentStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$from, $to->copy()->endOfDay()])
            ->groupBy('day_key')
            ->pluck('total', 'day_key');

        $series = [];
        for ($i = 0; $i < 30; $i++) {
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
                'obsolete' => $this->obsoleteDocumentCount($filtered),
            ],
            'publications' => $this->publicationSeries($filtered, $from, $to),
            'by_department' => $this->countGroups($filtered, 'department'),
            'by_document_type' => $this->countGroups($filtered, 'document_type'),
            'review' => $this->reviewHorizon($filtered),
            'mix' => $this->registerMix($filtered, array_keys($this->openRevisionIndex())),
            'aging' => $this->agingRows($filtered),
            'aging_summary' => $this->agingSummary($this->agingByDocumentCode($filtered)),
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
            if (is_string($status) && trim($status) === ControlledDocumentStatus::OBSOLETE) {
                $this->applyObsoleteDocumentScope($query);
            } elseif (is_string($status) && trim($status) !== '') {
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
     * Pending reviewers and approvers, aged from when they were assigned this round.
     *
     * @param  Builder<ControlledDocument>  $scoped
     * @return array<string, list<array{name: string, role: string, requestor_department: string|null, age_days: int, submitted_at: string|null, submission_id: string, document_no: string}>>
     */
    private function agingByDocumentCode(Builder $scoped): array
    {
        $documentDepartments = (clone $scoped)->pluck('department', 'document_code');
        $known = array_fill_keys($documentDepartments->keys()->map(static fn ($code): string => (string) $code)->all(), true);
        $grouped = [];

        $approvals = EApprovalRequestApproval::query()
            ->with([
                'approver:id,name',
                'step',
                'submission:id,document_no,created_at,status,requestor_id',
                'submission.requestor:id,name,department',
                'submission.values.field:id,name',
            ])
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->whereNotNull('approver_id')
            ->get();

        foreach ($approvals as $approval) {
            $submission = $approval->submission;
            if ($submission === null || (string) $submission->status !== EApprovalSubmissionStatus::PENDING) {
                continue;
            }
            $number = (string) $submission->document_no;
            $code = preg_match('/^(.+)-R\d+$/', $number, $matches) === 1 ? $matches[1] : $number;
            if (! isset($known[$code])) {
                continue;
            }
            $submitted = $approval->created_at ?? $submission->created_at;
            $documentDepartment = $documentDepartments->get($code);
            $grouped[$code][] = [
                'name' => (string) ($approval->approver?->name ?? '—'),
                'role' => EApprovalReviewStep::isReviewStep($approval->step) ? 'Reviewer' : 'Approver',
                'requestor_department' => $this->requestorDepartment(
                    $submission,
                    is_string($documentDepartment) ? $documentDepartment : null,
                ),
                'age_days' => $submitted !== null
                    ? max(0, (int) $submitted->copy()->startOfDay()->diffInDays(now()->startOfDay()))
                    : 0,
                'submitted_at' => $submitted?->toIso8601String(),
                'submission_id' => (string) $submission->id,
                'document_no' => $number,
            ];
        }

        return $grouped;
    }

    private function requestorDepartment(EApprovalSubmission $submission, ?string $documentDepartment): ?string
    {
        $fromUser = trim((string) ($submission->requestor?->department ?? ''));
        if ($fromUser !== '') {
            return $this->labels->department($fromUser);
        }

        if ($submission->relationLoaded('values')) {
            foreach ($submission->values as $value) {
                $name = $value->relationLoaded('field') ? $value->field?->name : null;
                if ($name !== 'department') {
                    continue;
                }
                $fromField = trim((string) ($value->value ?? ''));
                if ($fromField !== '') {
                    return $this->labels->department($fromField);
                }
            }
        }

        $fromDocument = trim((string) $documentDepartment);

        return $fromDocument !== '' ? $this->labels->department($fromDocument) : null;
    }

    /**
     * @param  Builder<ControlledDocument>  $scoped
     * @return list<array{document_code: string, name: string, role: string, requestor_department: string|null, age_days: int, submitted_at: string|null, submission_id: string, document_no: string}>
     */
    private function agingRows(Builder $scoped): array
    {
        return $this->agingRowsFrom($this->agingByDocumentCode($scoped));
    }

    /**
     * @param  array<string, list<array{name: string, role: string, requestor_department: string|null, age_days: int, submitted_at: string|null, submission_id: string, document_no: string}>>  $grouped
     * @return list<array{document_code: string, name: string, role: string, requestor_department: string|null, age_days: int, submitted_at: string|null, submission_id: string, document_no: string}>
     */
    private function agingRowsFrom(array $grouped): array
    {
        $rows = [];
        foreach ($grouped as $code => $people) {
            foreach ($people as $person) {
                $rows[] = ['document_code' => $code, ...$person];
            }
        }
        usort($rows, static fn (array $left, array $right): int => $right['age_days'] <=> $left['age_days']);

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  array<string, list<array{age_days: int}>>  $grouped
     * @return array{documents: int, people: int, oldest_days: int}
     */
    private function agingSummary(array $grouped): array
    {
        $people = 0;
        $oldest = 0;
        foreach ($grouped as $rows) {
            $people += count($rows);
            foreach ($rows as $row) {
                $oldest = max($oldest, (int) ($row['age_days'] ?? 0));
            }
        }

        return [
            'documents' => count($grouped),
            'people' => $people,
            'oldest_days' => $oldest,
        ];
    }

    /**
     * Each document lands in one slice. Obsolete wins, then a revision still in approval, then approved.
     *
     * @param  Builder<ControlledDocument>  $scoped
     * @param  list<string>  $incomingCodes
     * @return array{pending: int, approved: int, obsolete: int}
     */
    private function registerMix(Builder $scoped, array $incomingCodes): array
    {
        $obsolete = clone $scoped;
        $this->applyObsoleteDocumentScope($obsolete);
        $obsoleteIds = $obsolete->pluck('id');

        $pending = (clone $scoped)
            ->where(function (Builder $inner) use ($incomingCodes): void {
                $inner->where('status', ControlledDocumentStatus::PENDING);
                if ($incomingCodes !== []) {
                    $inner->orWhere(function (Builder $published) use ($incomingCodes): void {
                        $published->where('status', ControlledDocumentStatus::PUBLISHED)
                            ->whereIn('document_code', $incomingCodes);
                    });
                }
            })
            ->when(
                $obsoleteIds->isNotEmpty(),
                fn (Builder $query) => $query->whereNotIn('id', $obsoleteIds->all()),
            )
            ->count();

        $approved = (clone $scoped)
            ->where('status', ControlledDocumentStatus::PUBLISHED)
            ->when(
                $incomingCodes !== [],
                fn (Builder $query) => $query->whereNotIn('document_code', $incomingCodes),
            )
            ->when(
                $obsoleteIds->isNotEmpty(),
                fn (Builder $query) => $query->whereNotIn('id', $obsoleteIds->all()),
            )
            ->count();

        return [
            'pending' => $pending,
            'approved' => $approved,
            'obsolete' => $obsoleteIds->count(),
        ];
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

        return [
            'total' => (int) $rows->sum(),
            'pending' => $pending,
            'published' => $published,
            'obsolete' => $this->obsoleteDocumentCount($query),
        ];
    }

    /**
     * Withdrawn documents, plus published documents that still have an older revision.
     * Those older revisions are stored as superseded and shown as obsolete.
     *
     * @param  Builder<ControlledDocument>  $query
     */
    private function applyObsoleteDocumentScope(Builder $query): void
    {
        $statuses = $this->obsoleteRevisionStatuses();
        $query->where(function (Builder $inner) use ($statuses): void {
            $inner->whereIn('status', ControlledDocumentStatus::obsoleteValues())
                ->orWhereHas('revisions', static function (Builder $revisions) use ($statuses): void {
                    $revisions->whereIn('status', $statuses);
                });
        });
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     */
    private function obsoleteDocumentCount(Builder $query): int
    {
        $counted = clone $query;
        $this->applyObsoleteDocumentScope($counted);

        return $counted->count();
    }

    /**
     * @return list<string>
     */
    private function obsoleteRevisionStatuses(): array
    {
        return [ControlledDocumentRevisionStatus::SUPERSEDED, 'obsolete'];
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
     * @param  Builder<ControlledDocument>  $scoped
     * @param  list<string>  $incomingCodes
     * @return list<array{key: string, label: string, controlled: int, pending: int, approved: int, versioned: int, obsolete: int, obsolete_published: int, incoming: int, ongoing: int}>
     */
    private function departmentBreakdown(Builder $scoped, array $incomingCodes): array
    {
        $pending = ControlledDocumentStatus::PENDING;
        $published = ControlledDocumentStatus::PUBLISHED;
        $horizon = Carbon::today()->addDays(90)->toDateString();

        $rows = (clone $scoped)
            ->reorder()
            ->selectRaw("COALESCE(NULLIF(TRIM(department), ''), 'Unassigned') as bucket")
            ->selectRaw('COUNT(*) as controlled')
            ->selectRaw("SUM(CASE WHEN status = '{$pending}' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = '{$published}' THEN 1 ELSE 0 END) as approved")
            ->selectRaw('SUM(CASE WHEN current_revision > 0 THEN 1 ELSE 0 END) as versioned')
            ->selectRaw("SUM(CASE WHEN status = '{$published}' AND next_review_date IS NOT NULL AND next_review_date <= '{$horizon}' THEN 1 ELSE 0 END) as ongoing")
            ->groupBy('bucket')
            ->orderByDesc('controlled')
            ->limit(12)
            ->get();

        $obsoleteByDepartment = $this->obsoleteRevisionsByDepartment($scoped);
        $obsoletePublishedByDepartment = $this->documentsByDepartment(
            (clone $scoped)
                ->where('status', ControlledDocumentStatus::PUBLISHED)
                ->whereHas('revisions', function (Builder $revisions): void {
                    $revisions->whereIn('status', $this->obsoleteRevisionStatuses());
                }),
        );

        $incomingByDepartment = [];
        if ($incomingCodes !== []) {
            $incomingByDepartment = (clone $scoped)
                ->reorder()
                ->where('status', ControlledDocumentStatus::PUBLISHED)
                ->whereIn('document_code', $incomingCodes)
                ->selectRaw("COALESCE(NULLIF(TRIM(department), ''), 'Unassigned') as bucket")
                ->selectRaw('COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->all();
        }

        $items = [];
        foreach ($rows as $row) {
            $label = (string) $row->getAttribute('bucket');
            $items[] = [
                'key' => $label === 'Unassigned' ? '' : $label,
                'label' => $label,
                'controlled' => (int) $row->getAttribute('controlled'),
                'pending' => (int) $row->getAttribute('pending'),
                'approved' => (int) $row->getAttribute('approved'),
                'versioned' => (int) $row->getAttribute('versioned'),
                'obsolete' => (int) ($obsoleteByDepartment[$label] ?? 0),
                'obsolete_published' => (int) ($obsoletePublishedByDepartment[$label] ?? 0),
                'incoming' => (int) ($incomingByDepartment[$label] ?? 0),
                'ongoing' => (int) $row->getAttribute('ongoing'),
            ];
        }

        return $items;
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     * @return array<string, int>
     */
    private function documentsByDepartment(Builder $query): array
    {
        $counts = $query
            ->reorder()
            ->selectRaw("COALESCE(NULLIF(TRIM(department), ''), 'Unassigned') as bucket")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $byDepartment = [];
        foreach ($counts as $bucket => $total) {
            $byDepartment[(string) $bucket] = (int) $total;
        }

        return $byDepartment;
    }

    /**
     * Older revisions the file calls obsolete, counted once per revision.
     *
     * @param  Builder<ControlledDocument>  $scoped
     * @return array<string, int>
     */
    private function obsoleteRevisionsByDepartment(Builder $scoped): array
    {
        $counts = ControlledDocumentRevision::query()
            ->join('controlled_documents', 'controlled_documents.id', '=', 'controlled_document_revisions.controlled_document_id')
            ->whereIn('controlled_document_revisions.status', $this->obsoleteRevisionStatuses())
            ->whereIn('controlled_documents.id', (clone $scoped)->select('controlled_documents.id'))
            ->selectRaw("COALESCE(NULLIF(TRIM(controlled_documents.department), ''), 'Unassigned') as bucket")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $byDepartment = [];
        foreach ($counts as $bucket => $total) {
            $byDepartment[(string) $bucket] = (int) $total;
        }

        return $byDepartment;
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
