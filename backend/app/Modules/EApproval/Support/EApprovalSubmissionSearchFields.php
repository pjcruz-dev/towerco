<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Core\Support\ModuleListSearchDsl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared ModuleListSearchDsl field map for E-Forms submissions / exports.
 * Core keys plus allowlisted form-field names (workspace table columns, subsidiary, department).
 */
final class EApprovalSubmissionSearchFields
{
    /**
     * @param  list<string>  $formIds
     * @param  list<string>|null  $extraFieldNames  Workspace / visible form field names
     * @return array<string, array<string, mixed>>
     */
    public static function dslFields(array $formIds = [], ?array $extraFieldNames = null): array
    {
        $fields = [
            'status' => ['column' => 'status', 'type' => 'exact'],
            'document' => ['column' => 'document_no', 'type' => 'string'],
            'document_no' => ['column' => 'document_no', 'type' => 'string'],
            'title' => ['column' => 'document_no', 'type' => 'string'],
            'form' => [
                'relation' => 'form',
                'relation_column' => 'name',
                'type' => 'string',
            ],
            'requestor' => [
                'relation' => 'requestor',
                'relation_column' => 'name',
                'type' => 'string',
            ],
            'requester' => [
                'relation' => 'requestor',
                'relation_column' => 'name',
                'type' => 'string',
            ],
            'created' => ['column' => 'created_at', 'type' => 'exact'],
            'created_at' => ['column' => 'created_at', 'type' => 'exact'],
        ];

        $formFieldNames = array_values(array_unique(array_filter(array_merge(
            ['subsidiary', 'department'],
            $extraFieldNames ?? [],
        ), static fn (string $name): bool => trim($name) !== '')));

        foreach ($formFieldNames as $rawName) {
            $key = strtolower(trim($rawName));
            if ($key === '' || isset($fields[$key])) {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $fieldName = trim($rawName);
            $fields[$key] = [
                'handler' => static function (Builder $query, string $op, string $value) use ($formIds, $fieldName): void {
                    EApprovalSubmissionFieldFilter::applyDsl($query, $formIds, $fieldName, $op, $value);
                },
            ];
        }

        return $fields;
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     */
    public static function applyFreeText(Builder $query, string $residual): void
    {
        $like = '%'.addcslashes($residual, '%_\\').'%';
        $query->where(static function (Builder $q) use ($like): void {
            $q->where('document_no', 'like', $like)
                ->orWhereHas('form', static fn ($f) => $f->where('name', 'like', $like))
                ->orWhereHas('requestor', static fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
        });
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     * @param  list<string>  $formIds
     * @param  list<string>|null  $extraFieldNames
     */
    public static function applySearch(
        Builder $query,
        string $search,
        array $formIds = [],
        ?array $extraFieldNames = null,
    ): void {
        if (trim($search) === '') {
            return;
        }

        ModuleListSearchDsl::apply(
            $query,
            $search,
            self::dslFields($formIds, $extraFieldNames),
            static function (Builder $inner, string $residual): void {
                self::applyFreeText($inner, $residual);
            },
        );
    }
}
