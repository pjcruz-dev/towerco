<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EApprovalSubmissionExportService
{
    private const MAX_ROWS = 5000;

    /** @var list<string> */
    private const SKIP_FIELD_TYPES = [
        'section',
        'page_break',
        'divider',
        'info',
        'heading',
        'html',
        'file',
        'attachment',
        'signature',
    ];

    public function __construct(
        private readonly EApprovalFormValueDisplayService $valueDisplay,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(?EApprovalForm $form = null, bool $includeFields = true): array
    {
        $headers = $this->baseHeaders();

        if ($form === null || ! $includeFields) {
            return $headers;
        }

        foreach ($this->exportableFields($form) as $field) {
            $label = trim((string) ($field->label ?? ''));
            $headers[] = $label !== '' ? $label : (string) $field->name;
        }

        return $headers;
    }

    /**
     * @param  array{status?: string, form_id?: string, from?: string, to?: string, search?: string}  $filters
     * @param  array{viewer?: TenantUser, can_view_all?: bool, form?: EApprovalForm, include_fields?: bool}|null  $scope
     * @return Generator<int, list<string>>
     */
    public function rows(array $filters, ?array $scope = null): Generator
    {
        $form = $scope['form'] ?? null;
        $includeFields = ($scope['include_fields'] ?? true) === true;
        $exportFields = $form !== null && $includeFields ? $this->exportableFields($form) : collect();

        $query = $this->buildQuery($filters, $scope);
        if ($exportFields->isNotEmpty()) {
            $query->with(['values.field']);
        }

        foreach ($query->lazy(self::MAX_ROWS) as $submission) {
            yield $this->submissionRow($submission, $exportFields);
        }
    }

    /**
     * @return list<string>
     */
    private function baseHeaders(): array
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
     * @param  array{viewer?: TenantUser, can_view_all?: bool, form?: EApprovalForm, include_fields?: bool}|null  $scope
     */
    private function buildQuery(array $filters, ?array $scope): Builder
    {
        $query = EApprovalSubmission::query()
            ->with(['form:id,name', 'requestor:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(self::MAX_ROWS);

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['form_ids']) && is_array($filters['form_ids'])) {
            $query->whereIn('form_id', $filters['form_ids']);
        } elseif (! empty($filters['form_id'])) {
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
                    ->orWhereHas('form', static fn ($f) => $f->where('name', 'like', $like))
                    ->orWhereHas('requestor', static fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
            });
        }

        if ($scope !== null && isset($scope['viewer']) && ($scope['can_view_all'] ?? true) !== true) {
            $this->applyViewerScope($query, $scope['viewer']);
        }

        return $query;
    }

    private function applyViewerScope(Builder $query, TenantUser $viewer): void
    {
        $query->where(static function (Builder $scoped) use ($viewer): void {
            $scoped->where('requestor_id', $viewer->id)
                ->orWhereIn('id', EApprovalRequestApproval::query()
                    ->where('approver_id', $viewer->id)
                    ->select('submission_id'));
        });
    }

    /**
     * @param  Collection<int, EApprovalFormField>  $exportFields
     * @return list<string>
     */
    private function submissionRow(EApprovalSubmission $submission, Collection $exportFields): array
    {
        $row = [
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

        if ($exportFields->isEmpty()) {
            return $row;
        }

        $values = $submission->relationLoaded('values') ? $submission->values : collect();
        $valuesByFieldId = $values->keyBy(static fn ($value) => (string) $value->field_id);
        $usersById = $this->valueDisplay->approverUsersById($values);

        foreach ($exportFields as $field) {
            $value = $valuesByFieldId->get((string) $field->id);
            if ($value === null) {
                $row[] = '';

                continue;
            }

            $display = $this->valueDisplay->resolveDisplayValue(
                $field->type,
                $value->value,
                $usersById,
                is_array($field->options) ? $field->options : null,
            );
            $row[] = (string) ($display ?? '');
        }

        return $row;
    }

    /**
     * @return Collection<int, EApprovalFormField>
     */
    private function exportableFields(EApprovalForm $form): Collection
    {
        return EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->whereNotIn('type', self::SKIP_FIELD_TYPES)
            ->orderBy('step_order')
            ->orderBy('name')
            ->get();
    }
}
