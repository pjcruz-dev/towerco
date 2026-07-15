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

        $masterKey = trim((string) (
            $options['master_data_key']
            ?? $options['masterDataKey']
            ?? $options['lookup_key']
            ?? $options['lookupKey']
            ?? ''
        ));

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

            $choices[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
            ];
        }

        return $choices;
    }
}
