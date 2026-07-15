<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentRegistryService
{
    private const SORTABLE = [
        'document_code',
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
     * @return array{kpis: array<string, int>, documents: LengthAwarePaginator}
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
    ): array {
        $query = ControlledDocument::query()
            ->with(['createdBy:id,name']);

        $this->access->applyRegistryScope($query, $actor);
        $this->applyFilters($query, $search, $department, $status, $documentType);

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'document_code:asc'),
            self::SORTABLE,
            'document_code',
            'asc',
        );
        $query->orderBy($column, $direction);

        return [
            'kpis' => $this->kpis($actor),
            'documents' => $query->paginate(perPage: $perPage, page: $page),
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

    public function markObsolete(ControlledDocument $document, TenantUser $actor): ControlledDocument
    {
        if ($document->status === ControlledDocumentStatus::OBSOLETE) {
            return $document;
        }

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
        $published = (clone $query)->where('status', ControlledDocumentStatus::PUBLISHED)->count();
        $obsolete = (clone $query)->where('status', ControlledDocumentStatus::OBSOLETE)->count();

        return [
            'total' => $total,
            'published' => $published,
            'obsolete' => $obsolete,
        ];
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     */
    private function applyFilters(
        Builder $query,
        ?string $search,
        ?string $department,
        ?string $status,
        ?string $documentType,
    ): void {
        if (is_string($search) && trim($search) !== '') {
            $needle = '%'.strtolower(trim($search)).'%';
            $query->where(static function (Builder $inner) use ($needle): void {
                $inner->whereRaw('LOWER(document_code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$needle]);
            });
        }

        if (is_string($department) && trim($department) !== '') {
            $query->where('department', trim($department));
        }

        if (is_string($status) && trim($status) !== '') {
            $query->where('status', trim($status));
        }

        if (is_string($documentType) && trim($documentType) !== '') {
            $query->where('document_type', trim($documentType));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(ControlledDocument $document): array
    {
        return [
            'id' => (string) $document->id,
            'document_code' => $document->document_code,
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
            'revisions' => $document->revisions->map(static fn (ControlledDocumentRevision $revision): array => [
                'id' => (string) $revision->id,
                'revision_number' => (int) $revision->revision_number,
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
