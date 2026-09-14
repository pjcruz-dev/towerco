<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Core\Support\ModuleListSearchDsl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter submissions by scalar form field values (e.g. subsidiary, department).
 * Values live in e_approval_form_values; field identity is by field name across form_ids.
 */
final class EApprovalSubmissionFieldFilter
{
    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     * @param  list<string>  $formIds  Empty = match field name on any form
     */
    public static function applyEquals(Builder $query, array $formIds, string $fieldName, ?string $rawValue): void
    {
        self::applyDsl($query, $formIds, $fieldName, ModuleListSearchDsl::OP_EQ, (string) $rawValue);
    }

    /**
     * Apply a ModuleListSearchDsl operator against a form field value.
     * Pipe-separated values: OR for eq/contains, AND-exclude for ne.
     *
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     * @param  list<string>  $formIds
     */
    public static function applyDsl(
        Builder $query,
        array $formIds,
        string $fieldName,
        string $op,
        string $rawValue,
    ): void {
        $fieldName = trim($fieldName);
        $value = trim($rawValue);
        if ($fieldName === '' || $value === '') {
            return;
        }

        // Comparisons are not supported on free-text form values yet.
        if (in_array($op, [
            ModuleListSearchDsl::OP_GTE,
            ModuleListSearchDsl::OP_LTE,
            ModuleListSearchDsl::OP_GT,
            ModuleListSearchDsl::OP_LT,
        ], true)) {
            return;
        }

        $parts = self::splitOrValues($value);
        if ($parts === []) {
            return;
        }

        if ($op === ModuleListSearchDsl::OP_NE) {
            foreach ($parts as $part) {
                self::applySingleNe($query, $formIds, $fieldName, $part);
            }

            return;
        }

        if (count($parts) === 1) {
            self::applySingleMatch($query, $formIds, $fieldName, $op, $parts[0]);

            return;
        }

        $query->where(static function (Builder $group) use ($formIds, $fieldName, $op, $parts): void {
            foreach ($parts as $index => $part) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $group->{$method}(static function (Builder $inner) use ($formIds, $fieldName, $op, $part): void {
                    self::applySingleMatch($inner, $formIds, $fieldName, $op, $part);
                });
            }
        });
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     * @param  list<string>  $formIds
     * @param  array{subsidiary?: string|null, department?: string|null}  $filters
     */
    public static function applyWorkspaceColumnFilters(Builder $query, array $formIds, array $filters): void
    {
        if (isset($filters['subsidiary']) && is_string($filters['subsidiary']) && trim($filters['subsidiary']) !== '') {
            self::applyEquals($query, $formIds, 'subsidiary', $filters['subsidiary']);
        }

        if (isset($filters['department']) && is_string($filters['department']) && trim($filters['department']) !== '') {
            self::applyEquals($query, $formIds, 'department', $filters['department']);
        }
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     * @param  list<string>  $formIds
     */
    private static function applySingleMatch(
        Builder $query,
        array $formIds,
        string $fieldName,
        string $op,
        string $value,
    ): void {
        if ($op === ModuleListSearchDsl::OP_CONTAINS) {
            $like = '%'.addcslashes($value, '%_\\').'%';
            $query->whereHas('values', static function (Builder $values) use ($formIds, $fieldName, $like): void {
                $values->whereHas('field', static function (Builder $field) use ($formIds, $fieldName): void {
                    self::constrainFieldName($field, $formIds, $fieldName);
                })->where('value', 'like', $like);
            });

            return;
        }

        // eq (and default)
        $candidates = array_values(array_unique([
            $value,
            mb_strtoupper($value),
            mb_strtolower($value),
        ]));

        $query->whereHas('values', static function (Builder $values) use ($formIds, $fieldName, $candidates): void {
            $values->whereHas('field', static function (Builder $field) use ($formIds, $fieldName): void {
                self::constrainFieldName($field, $formIds, $fieldName);
            })->where(static function (Builder $match) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $match->orWhere('value', $candidate);
                }
            });
        });
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     * @param  list<string>  $formIds
     */
    private static function applySingleNe(
        Builder $query,
        array $formIds,
        string $fieldName,
        string $value,
    ): void {
        $candidates = array_values(array_unique([
            $value,
            mb_strtoupper($value),
            mb_strtolower($value),
        ]));

        $query->where(static function (Builder $outer) use ($formIds, $fieldName, $candidates): void {
            $outer
                ->whereDoesntHave('values', static function (Builder $values) use ($formIds, $fieldName, $candidates): void {
                    $values->whereHas('field', static function (Builder $field) use ($formIds, $fieldName): void {
                        self::constrainFieldName($field, $formIds, $fieldName);
                    })->where(static function (Builder $match) use ($candidates): void {
                        foreach ($candidates as $candidate) {
                            $match->orWhere('value', $candidate);
                        }
                    });
                });
        });
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalFormField>  $field
     * @param  list<string>  $formIds
     */
    private static function constrainFieldName(Builder $field, array $formIds, string $fieldName): void
    {
        $field->where('name', $fieldName);
        if ($formIds !== []) {
            $field->whereIn('form_id', $formIds);
        }
    }

    /** @return list<string> */
    private static function splitOrValues(string $value): array
    {
        if (! str_contains($value, '|')) {
            return [$value];
        }

        $parts = [];
        foreach (explode('|', $value) as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        return $parts === [] ? [$value] : array_values(array_unique($parts));
    }
}
