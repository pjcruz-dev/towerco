<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceDashboardSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Collection;

final class EApprovalFormWorkspaceSubmissionService
{
    public function __construct(
        private readonly EApprovalSubmissionService $submissions,
        private readonly EApprovalFormValueDisplayService $valueDisplay,
    ) {}

    /**
     * @param  array<string, mixed>  $workspace
     * @param  list<string>  $formIds
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function paginate(
        EApprovalForm $primaryForm,
        array $workspace,
        array $formIds,
        TenantUser $viewer,
        bool $canViewAll,
        int $page,
        int $perPage,
        string $search,
        ?string $status,
        ?string $from = null,
        ?string $to = null,
        ?string $sort = null,
    ): array {
        $dashboard = is_array($workspace['dashboard'] ?? null) ? $workspace['dashboard'] : [];
        $columns = is_array($dashboard['table_columns'] ?? null) ? $dashboard['table_columns'] : [];
        $fieldColumns = EApprovalFormWorkspaceDashboardSupport::visibleFieldColumns($columns);
        $fieldNames = array_map(static fn (array $column): string => $column['field_name'], $fieldColumns);
        $fieldsByName = $this->fieldsByName($primaryForm, $fieldNames);

        $paginator = $this->submissions->paginate(
            $viewer,
            $page,
            $perPage,
            $search,
            $status,
            $canViewAll,
            null,
            $from,
            $to,
            $fieldNames !== [] ? $fieldNames : null,
            $formIds,
            $sort,
        );

        $rows = $paginator->getCollection()->map(function (EApprovalSubmission $submission) use ($fieldsByName, $fieldNames): array {
            $row = $submission->toListRow();
            $row['field_values'] = $this->fieldValuesForSubmission($submission, $fieldsByName, $fieldNames);

            return $row;
        })->values()->all();

        return [
            'data' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @param  list<string>  $fieldNames
     * @return Collection<string, EApprovalFormField>
     */
    private function fieldsByName(EApprovalForm $form, array $fieldNames): Collection
    {
        if ($fieldNames === []) {
            return collect();
        }

        return EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->whereIn('name', $fieldNames)
            ->get()
            ->keyBy(static fn (EApprovalFormField $field): string => (string) $field->name);
    }

    /**
     * @param  Collection<string, EApprovalFormField>  $fieldsByName
     * @param  list<string>  $fieldNames
     * @return array<string, string|null>
     */
    private function fieldValuesForSubmission(
        EApprovalSubmission $submission,
        Collection $fieldsByName,
        array $fieldNames,
    ): array {
        if ($fieldNames === []) {
            return [];
        }

        $fieldIds = $fieldsByName->pluck('id')->all();
        $values = $submission->relationLoaded('values')
            ? $submission->values->whereIn('field_id', $fieldIds)
            : EApprovalFormValue::query()
                ->where('submission_id', $submission->id)
                ->whereIn('field_id', $fieldIds)
                ->with('field')
                ->get();

        $usersById = $this->valueDisplay->approverUsersById($values);
        $valuesByFieldId = $values->keyBy(static fn (EApprovalFormValue $value): string => (string) $value->field_id);

        $mapped = [];
        foreach ($fieldNames as $fieldName) {
            $field = $fieldsByName->get($fieldName);
            if ($field === null) {
                $mapped[$fieldName] = null;

                continue;
            }

            $value = $valuesByFieldId->get((string) $field->id);
            if ($value === null) {
                $mapped[$fieldName] = null;

                continue;
            }

            $mapped[$fieldName] = $this->valueDisplay->resolveDisplayValue(
                $field->type,
                $value->value,
                $usersById,
                is_array($field->options) ? $field->options : null,
            );
        }

        return $mapped;
    }
}
