<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use Generator;
use Illuminate\Database\Eloquent\Builder;

final class EApprovalSubmissionExportService
{
    private const MAX_ROWS = 5000;

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'id',
            'document_no',
            'form_id',
            'form_name',
            'requestor_id',
            'requestor_name',
            'requestor_email',
            'status',
            'current_step',
            'parent_submission_id',
            'created_at',
        ];
    }

    /**
     * @param  array{status?: string, form_id?: string, from?: string, to?: string, search?: string}  $filters
     * @return Generator<int, list<string>>
     */
    public function rows(array $filters): Generator
    {
        $query = EApprovalSubmission::query()
            ->with(['form:id,name', 'requestor:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(self::MAX_ROWS);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(static function (Builder $q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhereHas('form', static fn ($f) => $f->where('name', 'like', $like));
            });
        }

        foreach ($query->cursor() as $submission) {
            yield [
                (string) $submission->id,
                (string) $submission->document_no,
                (string) $submission->form_id,
                (string) ($submission->form?->name ?? ''),
                (string) $submission->requestor_id,
                (string) ($submission->requestor?->name ?? ''),
                (string) ($submission->requestor?->email ?? ''),
                (string) $submission->status,
                (string) $submission->current_step,
                (string) ($submission->parent_submission_id ?? ''),
                $submission->created_at?->toIso8601String() ?? '',
            ];
        }
    }
}
