<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use Illuminate\Validation\ValidationException;

final class EApprovalFormFieldChoicesResolver
{
    public function __construct(
        private readonly EApprovalMasterDataService $masterData,
    ) {}

    /**
     * @return list<array{value: string, label: string}>
     */
    public function choicesForFieldName(EApprovalForm $form, string $fieldName): array
    {
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            return [];
        }

        $form->loadMissing('fields');
        $field = $form->fields->firstWhere('name', $fieldName);
        if ($field === null) {
            return [];
        }

        $options = is_array($field->options) ? $field->options : [];
        $choices = EApprovalFieldOptionsParser::selectChoices($options);
        if ($choices !== []) {
            return $choices;
        }

        return $this->choicesFromMasterKey($this->masterDataKeyFromOptions($options));
    }

    /**
     * Resolve master-data lookups into static `choices` for unauthenticated public forms.
     * Strips lookup keys so the public UI never calls authenticated master-data APIs.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public function hydrateFieldsForPublicPayload(array $fields): array
    {
        $hydrated = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $options = is_array($field['options'] ?? null) ? $field['options'] : [];
            if ($options !== [] && ! array_is_list($options)) {
                $field['options'] = $this->hydrateOptionsBag($options);
            }

            $hydrated[] = $field;
        }

        return $hydrated;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function hydrateOptionsBag(array $options): array
    {
        $masterKey = $this->masterDataKeyFromOptions($options);
        if ($masterKey !== '') {
            $resolved = $this->choicesFromMasterKey($masterKey);
            if ($resolved !== []) {
                $options['choices'] = $resolved;
            }
        }

        if (is_array($options['columns'] ?? null)) {
            $options['columns'] = array_map(
                function (mixed $column): mixed {
                    if (! is_array($column)) {
                        return $column;
                    }

                    $colKey = $this->masterDataKeyFromOptions($column);
                    if ($colKey !== '') {
                        $resolved = $this->choicesFromMasterKey($colKey);
                        if ($resolved !== []) {
                            $column['choices'] = $resolved;
                        }
                    }

                    return $this->stripMasterDataKeys($column);
                },
                $options['columns'],
            );
        }

        return $this->stripMasterDataKeys($options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function stripMasterDataKeys(array $options): array
    {
        unset(
            $options['master_data_key'],
            $options['masterDataKey'],
            $options['lookup_key'],
            $options['lookupKey'],
        );

        return $options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function masterDataKeyFromOptions(array $options): string
    {
        return trim((string) (
            $options['master_data_key']
            ?? $options['masterDataKey']
            ?? $options['lookup_key']
            ?? $options['lookupKey']
            ?? ''
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function choicesFromMasterKey(string $masterKey): array
    {
        if ($masterKey === '') {
            return [];
        }

        try {
            $lookup = $this->masterData->lookupByKey($masterKey);
        } catch (ValidationException) {
            return [];
        }

        $rows = is_array($lookup['options'] ?? null) ? $lookup['options'] : [];
        $choices = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = trim((string) ($row['value'] ?? $row['code'] ?? $row['label'] ?? ''));
            if ($value === '') {
                continue;
            }

            $label = trim((string) ($row['label'] ?? $value));
            $choice = [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
            ];

            $subtitle = $row['subtitle'] ?? null;
            if (is_string($subtitle) && trim($subtitle) !== '') {
                $choice['subtitle'] = trim($subtitle);
            }

            $choices[] = $choice;
        }

        return $choices;
    }
}
