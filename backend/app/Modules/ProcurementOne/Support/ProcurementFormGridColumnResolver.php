<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use App\Modules\EApproval\Models\EApprovalForm;

final class ProcurementFormGridColumnResolver
{
    /**
     * @param  list<string>  $fallback
     * @return list<string>
     */
    public function labelsForField(EApprovalForm $form, string $fieldName, array $fallback): array
    {
        $form->loadMissing('fields');
        $field = $form->fields->firstWhere('name', $fieldName);
        if ($field === null) {
            return $fallback;
        }

        $options = is_array($field->options) ? $field->options : [];
        $columns = $options['columns'] ?? null;
        if (! is_array($columns)) {
            return $fallback;
        }

        $labels = [];
        foreach ($columns as $column) {
            if (is_string($column) && trim($column) !== '') {
                $labels[] = trim($column);

                continue;
            }

            if (is_array($column) && isset($column['label']) && is_string($column['label']) && trim($column['label']) !== '') {
                $labels[] = trim($column['label']);
            }
        }

        return $labels !== [] ? $labels : $fallback;
    }
}
