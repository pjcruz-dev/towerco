<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use Illuminate\Validation\ValidationException;

final class EApprovalSubmissionValuesValidator
{
    public function __construct(
        private readonly EApprovalFieldVisibilityEvaluator $visibility,
        private readonly EApprovalApprovalPolicyRequiredApproverFields $policyRequiredApprovers,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $attachmentCountsByFieldName
     * @param  array<string, list<string>>  $attachmentSlotsByFieldName
     */
    public function validate(
        EApprovalForm $form,
        array $values,
        bool $requireRequired = true,
        array $attachmentCountsByFieldName = [],
        array $attachmentSlotsByFieldName = [],
    ): void {
        $form->loadMissing('fields');
        $errors = [];
        $policyApproverFields = $this->policyRequiredApprovers->fieldNamesForValidation($form, $values);

        foreach ($form->fields as $field) {
            if ($this->isStructuralField($field)) {
                continue;
            }

            if (! $this->visibility->isVisible($field, $values)) {
                continue;
            }

            $name = (string) $field->name;
            $raw = $values[$name] ?? $values[(string) $field->id] ?? null;
            $value = $this->normalizeValue($raw);
            $validation = is_array($field->validation) ? $field->validation : [];
            $label = trim((string) $field->label) ?: $name;
            $type = (string) $field->type;
            $isRequired = $type === 'approver' && $policyApproverFields !== null
                ? in_array($name, $policyApproverFields, true)
                : (($validation['required'] ?? false) === true);

            if ($type === 'file' || $type === 'camera') {
                if ($requireRequired) {
                    $count = (int) ($attachmentCountsByFieldName[$name] ?? 0);
                    $options = is_array($field->options) ? $field->options : [];
                    $min = $type === 'camera' && is_numeric($options['min'] ?? null)
                        ? max(0, (int) $options['min'])
                        : 0;
                    $required = (($validation['required'] ?? false) === true) || $min > 0;

                    if ($required && $count <= 0) {
                        $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];
                    } elseif ($type === 'camera' && $min > 0 && $count < $min) {
                        $errors["values.{$name}"] = [__(
                            ':label requires at least :min photo(s).',
                            ['label' => $label, 'min' => $min],
                        )];
                    }

                    if ($type === 'camera' && is_numeric($options['max'] ?? null)) {
                        $max = max(1, (int) $options['max']);
                        if ($count > $max) {
                            $errors["values.{$name}"] = [__(
                                ':label allows at most :max photo(s).',
                                ['label' => $label, 'max' => $max],
                            )];
                        }
                    }

                    if ($type === 'camera' && $requireRequired) {
                        $slots = $this->normalizeCameraSlots($options['slots'] ?? null);
                        if ($slots !== []) {
                            $present = $attachmentSlotsByFieldName[$name] ?? [];
                            $missing = [];
                            foreach ($slots as $slot) {
                                if (! in_array($slot, $present, true)) {
                                    $missing[] = $slot;
                                }
                            }
                            if ($missing !== []) {
                                $errors["values.{$name}"] = [__(
                                    ':label is missing photo(s) for: :slots.',
                                    ['label' => $label, 'slots' => implode(', ', $missing)],
                                )];
                            }
                        }
                    }
                }

                continue;
            }

            if ($type === 'grid') {
                if ($requireRequired && (($validation['required'] ?? false) === true) && ! $this->gridHasContent($raw, $field)) {
                    $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];
                }

                continue;
            }

            if ($type === 'matrix') {
                $options = is_array($field->options) ? $field->options : null;
                $matrixError = $this->validateMatrixValue(
                    $value,
                    $options,
                    $label,
                    $requireRequired && $isRequired,
                );
                if ($matrixError !== null) {
                    $errors["values.{$name}"] = [$matrixError];
                }

                continue;
            }

            if ($type === 'size_matrix') {
                $options = is_array($field->options) ? $field->options : null;
                $sizeError = $this->validateSizeMatrixValue(
                    $value,
                    $options,
                    $label,
                    $requireRequired && $isRequired,
                );
                if ($sizeError !== null) {
                    $errors["values.{$name}"] = [$sizeError];
                }

                continue;
            }

            if ($type === 'checklist_matrix') {
                $options = is_array($field->options) ? $field->options : null;
                $checklistError = $this->validateChecklistMatrixValue(
                    $value,
                    $options,
                    $label,
                    $requireRequired && $isRequired,
                );
                if ($checklistError !== null) {
                    $errors["values.{$name}"] = [$checklistError];
                }

                continue;
            }

            if ($type === 'checkbox') {
                $options = is_array($field->options) ? $field->options : null;
                if ($this->isCheckboxMulti($options)) {
                    $state = $this->parseCheckboxState($value);
                    $selected = $state['selected'];
                    if ($requireRequired && $isRequired && $selected === []) {
                        $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];
                        continue;
                    }

                    $staticChoices = EApprovalFieldOptionsParser::selectChoices($options);
                    if ($selected !== [] && $staticChoices !== []) {
                        $allowed = array_column($staticChoices, 'value');
                        foreach ($selected as $choiceValue) {
                            if (! in_array($choiceValue, $allowed, true)) {
                                $errors["values.{$name}"] = [__(':label contains an invalid option.', ['label' => $label])];
                                continue 2;
                            }
                        }

                        $companionError = $this->validateCheckboxCompanions($state, $staticChoices, $label);
                        if ($companionError !== null) {
                            $errors["values.{$name}"] = [$companionError];
                        }
                    }

                    continue;
                }

                if ($requireRequired && $isRequired && ! $this->isCheckboxTruthy($value)) {
                    $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];
                }

                continue;
            }

            if ($type === 'date_range') {
                $rangeError = $this->validateDateRange(
                    $value,
                    $requireRequired && $isRequired,
                    $label,
                );
                if ($rangeError !== null) {
                    $errors["values.{$name}"] = [$rangeError];
                }

                continue;
            }

            if ($type === 'approver_list') {
                $ids = \App\Modules\EApproval\Support\EApprovalUserListValueParser::parse($raw);
                if ($requireRequired && $isRequired && $ids === []) {
                    $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];

                    continue;
                }

                $options = is_array($field->options) ? $field->options : [];
                $min = is_numeric($options['min'] ?? null) ? max(0, (int) $options['min']) : 0;
                $max = is_numeric($options['max'] ?? null) ? max(0, (int) $options['max']) : 0;
                if ($min > 0 && count($ids) < $min) {
                    $errors["values.{$name}"] = [__(
                        ':label requires at least :min approver(s).',
                        ['label' => $label, 'min' => $min],
                    )];

                    continue;
                }
                if ($max > 0 && count($ids) > $max) {
                    $errors["values.{$name}"] = [__(
                        ':label allows at most :max approver(s).',
                        ['label' => $label, 'max' => $max],
                    )];

                    continue;
                }

                continue;
            }

            if ($requireRequired && $isRequired && $value === '') {
                $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];

                continue;
            }

            if ($value === '') {
                continue;
            }

            $maxLength = isset($validation['max_length']) ? (int) $validation['max_length'] : 0;
            if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
                $errors["values.{$name}"] = [__(':label must be at most :max characters.', ['label' => $label, 'max' => $maxLength])];
            }

            if ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors["values.{$name}"] = [__(':label must be a valid email address.', ['label' => $label])];
            }

            if ($type === 'phone' && ! $this->isValidPhone($value)) {
                $errors["values.{$name}"] = [__(':label must be a valid phone number.', ['label' => $label])];
            }

            if ($type === 'url' && ! $this->isValidUrl($value)) {
                $errors["values.{$name}"] = [__(':label must be a valid URL.', ['label' => $label])];
            }

            if ($type === 'rating' && ! $this->isValidRating($field, $value)) {
                $errors["values.{$name}"] = [__(':label must be a valid rating.', ['label' => $label])];
            }

            if ($type === 'location' && ! $this->isValidLocation($value)) {
                $errors["values.{$name}"] = [__(':label must include valid coordinates.', ['label' => $label])];
            }

            if ($type === 'tags' && ! $this->isValidTags($value, $requireRequired && ($validation['required'] ?? false) === true)) {
                $errors["values.{$name}"] = [__(':label must include at least one tag.', ['label' => $label])];
            }

            if ($type === 'signature' && ! $this->isValidSignature($value)) {
                $errors["values.{$name}"] = [__(':label must include a signature.', ['label' => $label])];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isStructuralField(EApprovalFormField $field): bool
    {
        return in_array((string) $field->type, ['section', 'divider', 'page_break', 'instruction'], true);
    }

    /**
     * @return list<string>
     */
    private function normalizeCameraSlots(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $slots = [];
        foreach ($raw as $item) {
            $slot = trim((string) $item);
            if ($slot !== '') {
                $slots[] = mb_substr($slot, 0, 120);
            }
        }

        return array_values(array_unique($slots));
    }

    private function normalizeValue(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_bool($raw)) {
            return $raw ? 'true' : 'false';
        }

        if (is_scalar($raw)) {
            return trim((string) $raw);
        }

        return trim(json_encode($raw, JSON_THROW_ON_ERROR) ?: '');
    }

    private function isValidPhone(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    private function isValidUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        return filter_var('https://'.$value, FILTER_VALIDATE_URL) !== false;
    }

    private function isValidRating(EApprovalFormField $field, string $value): bool
    {
        if (! ctype_digit($value)) {
            return false;
        }

        $rating = (int) $value;
        $options = is_array($field->options) ? $field->options : [];
        $max = max(1, min(10, (int) ($options['max_stars'] ?? 5)));

        return $rating >= 1 && $rating <= $max;
    }

    private function isValidLocation(string $value): bool
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded) && isset($decoded['lat'], $decoded['lng'])) {
            return is_numeric($decoded['lat']) && is_numeric($decoded['lng']);
        }

        return (bool) preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $value);
    }

    private function validateDateRange(string $value, bool $required, string $label): ?string
    {
        $from = '';
        $to = '';
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $required
                ? __(':label requires a start and end date.', ['label' => $label])
                : null;
        }

        try {
            $parsed = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($parsed)) {
                $from = trim((string) ($parsed['from'] ?? ''));
                $to = trim((string) ($parsed['to'] ?? ''));
            }
        } catch (\Throwable) {
            if (str_contains($trimmed, '|')) {
                [$fromRaw, $toRaw] = array_pad(explode('|', $trimmed, 2), 2, '');
                $from = trim((string) $fromRaw);
                $to = trim((string) $toRaw);
            } else {
                return __(':label must use valid dates.', ['label' => $label]);
            }
        }

        if ($required && ($from === '' || $to === '')) {
            return __(':label requires a start and end date.', ['label' => $label]);
        }

        if (($from === '') !== ($to === '')) {
            return __(':label requires both start and end dates.', ['label' => $label]);
        }

        if ($from === '' && $to === '') {
            return null;
        }

        if (! $this->isIsoDate($from) || ! $this->isIsoDate($to)) {
            return __(':label must use valid dates.', ['label' => $label]);
        }

        if ($to < $from) {
            return __(':label: end date must be on or after the start date.', ['label' => $label]);
        }

        return null;
    }

    private function isIsoDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $parts = explode('-', $value);
        if (count($parts) !== 3) {
            return false;
        }

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function isValidTags(string $value, bool $required): bool
    {
        if ($value === '') {
            return ! $required;
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            $count = is_array($decoded)
                ? count(array_filter($decoded, static fn ($t) => trim((string) $t) !== ''))
                : 0;

            return $count > 0;
        }

        return count(array_filter(array_map('trim', explode(',', $value)))) > 0;
    }

    private function isValidSignature(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, 'data:image/')) {
            return strlen($trimmed) <= 500000;
        }

        return strlen($trimmed) <= 5000;
    }

    private function gridHasContent(mixed $raw, EApprovalFormField $field): bool
    {
        $rows = $this->gridRows($raw);
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $cell) {
                if (is_scalar($cell) && trim((string) $cell) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<mixed>
     */
    private function gridRows(mixed $raw): array
    {
        if (is_array($raw)) {
            if (array_is_list($raw)) {
                return $raw;
            }

            $rows = $raw['rows'] ?? null;

            return is_array($rows) ? array_values($rows) : [];
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        $rows = $decoded['rows'] ?? null;

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function isCheckboxMulti(?array $options): bool
    {
        if ($options === null || $options === []) {
            return false;
        }

        if (EApprovalFieldOptionsParser::selectChoices($options) !== []) {
            return true;
        }

        $masterKey = trim((string) (
            $options['master_data_key']
            ?? $options['masterDataKey']
            ?? $options['lookup_key']
            ?? $options['lookupKey']
            ?? ''
        ));

        return $masterKey !== '';
    }

    private function isCheckboxTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * @return array{selected: list<string>, companions: array<string, array<string, string>>}
     */
    private function parseCheckboxState(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return ['selected' => [], 'companions' => []];
        }

        if (str_starts_with($trimmed, '{')) {
            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }

            if (is_array($decoded)) {
                $selectedRaw = $decoded['selected'] ?? null;
                $selected = [];
                $seen = [];
                if (is_array($selectedRaw)) {
                    foreach ($selectedRaw as $item) {
                        $part = trim((string) $item);
                        if ($part === '' || isset($seen[$part])) {
                            continue;
                        }
                        $seen[$part] = true;
                        $selected[] = $part;
                    }
                }

                return [
                    'selected' => $selected,
                    'companions' => $this->normalizeCheckboxCompanions($decoded['companions'] ?? null),
                ];
            }
        }

        return [
            'selected' => $this->parseCheckboxValues($trimmed),
            'companions' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function parseCheckboxValues(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $seen = [];
        $out = [];
        foreach (explode(',', $value) as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }
            $seen[$trimmed] = true;
            $out[] = $trimmed;
        }

        return $out;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function normalizeCheckboxCompanions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $choiceValue => $fields) {
            $choiceKey = trim((string) $choiceValue);
            if ($choiceKey === '' || ! is_array($fields)) {
                continue;
            }
            $normalized = [];
            foreach ($fields as $inputKey => $inputValue) {
                $key = trim((string) $inputKey);
                if ($key === '') {
                    continue;
                }
                $normalized[$key] = is_scalar($inputValue) ? (string) $inputValue : '';
            }
            $out[$choiceKey] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array{selected: list<string>, companions: array<string, array<string, string>>}  $state
     * @param  list<array{value: string, label: string, inputs?: list<array{key: string, type: string, suffix?: string, placeholder?: string, required?: bool}>}>  $choices
     */
    private function validateCheckboxCompanions(array $state, array $choices, string $label): ?string
    {
        $byValue = [];
        foreach ($choices as $choice) {
            $byValue[$choice['value']] = $choice;
        }

        foreach ($state['selected'] as $selectedValue) {
            $choice = $byValue[$selectedValue] ?? null;
            $inputs = is_array($choice['inputs'] ?? null) ? $choice['inputs'] : [];
            if ($inputs === []) {
                continue;
            }

            foreach ($inputs as $input) {
                $key = (string) ($input['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $raw = trim((string) ($state['companions'][$selectedValue][$key] ?? ''));
                $required = ($input['required'] ?? false) === true;
                if ($raw !== '' && ($input['type'] ?? 'text') === 'size') {
                    $size = $this->parseCompanionSizeValue($raw);
                    $na = ($size['na'] ?? false) === true;
                    $w = trim((string) ($size['w'] ?? ''));
                    $h = trim((string) ($size['h'] ?? ''));
                    if ($required && ! $na && ($w === '' || $h === '')) {
                        $choiceLabel = trim((string) ($choice['label'] ?? $selectedValue));

                        return __(':label: enter size or NA for :choice.', [
                            'label' => $label,
                            'choice' => $choiceLabel,
                        ]);
                    }
                    if (! $na && (($w !== '' && $h === '') || ($w === '' && $h !== ''))) {
                        $choiceLabel = trim((string) ($choice['label'] ?? $selectedValue));

                        return __(':label: enter both width and height for :choice, or mark NA.', [
                            'label' => $label,
                            'choice' => $choiceLabel,
                        ]);
                    }
                    if (! $na && (($w !== '' && ! is_numeric($w)) || ($h !== '' && ! is_numeric($h)))) {
                        $choiceLabel = trim((string) ($choice['label'] ?? $selectedValue));

                        return __(':label: :choice sizes must be valid numbers.', [
                            'label' => $label,
                            'choice' => $choiceLabel,
                        ]);
                    }

                    continue;
                }

                if ($required && $raw === '') {
                    $hint = trim((string) ($input['suffix'] ?? $input['placeholder'] ?? $key));
                    $choiceLabel = trim((string) ($choice['label'] ?? $selectedValue));

                    return __(':label: enter a value for :choice (:hint).', [
                        'label' => $label,
                        'choice' => $choiceLabel,
                        'hint' => $hint !== '' ? $hint : $key,
                    ]);
                }

                if ($raw !== '' && ($input['type'] ?? 'text') === 'number' && ! is_numeric($raw)) {
                    $choiceLabel = trim((string) ($choice['label'] ?? $selectedValue));

                    return __(':label: :choice must be a valid number.', [
                        'label' => $label,
                        'choice' => $choiceLabel,
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * @return array{w?: string, h?: string, na?: bool}
     */
    private function parseCompanionSizeValue(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        if (strtolower($trimmed) === 'na') {
            return ['na' => true];
        }
        if (! str_starts_with($trimmed, '{')) {
            return [];
        }
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (! is_array($decoded)) {
            return [];
        }
        if (($decoded['na'] ?? false) === true) {
            return ['na' => true];
        }
        $out = [];
        $w = trim((string) ($decoded['w'] ?? ''));
        $h = trim((string) ($decoded['h'] ?? ''));
        if ($w !== '') {
            $out['w'] = $w;
        }
        if ($h !== '') {
            $out['h'] = $h;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function validateMatrixValue(string $value, ?array $options, string $label, bool $required): ?string
    {
        $axes = EApprovalFieldOptionsParser::matrixAxes($options);
        $rowValues = array_column($axes['rows'], 'value');
        $columnValues = array_column($axes['columns'], 'value');
        $state = $this->parseMatrixValue($value);

        foreach ($state as $rowKey => $columnValue) {
            if (! in_array($rowKey, $rowValues, true) || ! in_array($columnValue, $columnValues, true)) {
                return __(':label contains an invalid answer.', ['label' => $label]);
            }
        }

        if (! $required) {
            return null;
        }

        foreach ($rowValues as $rowKey) {
            if (! isset($state[$rowKey]) || trim((string) $state[$rowKey]) === '') {
                return __(':label requires an answer for every row.', ['label' => $label]);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function parseMatrixValue(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [];
        }

        $out = [];
        if (isset($decoded['answers']) && is_array($decoded['answers'])) {
            foreach ($decoded['answers'] as $rowKey => $columnValue) {
                $row = trim((string) $rowKey);
                $column = trim((string) $columnValue);
                if ($row === '' || $column === '') {
                    continue;
                }
                $out[$row] = $column;
            }

            return $out;
        }

        foreach ($decoded as $rowKey => $columnValue) {
            $row = trim((string) $rowKey);
            if ($row === '' || $row === 'answers' || $row === 'notes') {
                continue;
            }
            if (is_array($columnValue)) {
                $column = trim((string) ($columnValue['value'] ?? $columnValue['v'] ?? ''));
            } else {
                $column = trim((string) $columnValue);
            }
            if ($column === '') {
                continue;
            }
            $out[$row] = $column;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function validateSizeMatrixValue(string $value, ?array $options, string $label, bool $required): ?string
    {
        $rows = EApprovalFieldOptionsParser::sizeMatrixRows($options);
        $rowsByValue = [];
        foreach ($rows as $row) {
            $rowsByValue[$row['value']] = $row;
        }
        $state = $this->parseSizeMatrixValue($value);

        foreach ($state as $rowKey => $rowValue) {
            if (! isset($rowsByValue[$rowKey])) {
                return __(':label contains an invalid answer.', ['label' => $label]);
            }

            $input = ($rowsByValue[$rowKey]['input'] ?? 'size') === 'text' ? 'text' : 'size';
            if ($input === 'text') {
                continue;
            }

            $na = ($rowValue['na'] ?? false) === true;
            $w = trim((string) ($rowValue['w'] ?? ''));
            $h = trim((string) ($rowValue['h'] ?? ''));

            if ($na) {
                continue;
            }

            if (($w !== '' && ! is_numeric($w)) || ($h !== '' && ! is_numeric($h))) {
                return __(':label sizes must be valid numbers.', ['label' => $label]);
            }

            if (($w !== '' && $h === '') || ($w === '' && $h !== '')) {
                return __(':label: enter both width and height, or mark NA.', ['label' => $label]);
            }
        }

        if ($required) {
            foreach ($rows as $row) {
                if (($row['input'] ?? 'size') === 'text') {
                    continue;
                }

                $rowKey = $row['value'];
                $entry = $state[$rowKey] ?? null;
                $na = is_array($entry) && ($entry['na'] ?? false) === true;
                $w = is_array($entry) ? trim((string) ($entry['w'] ?? '')) : '';
                $h = is_array($entry) ? trim((string) ($entry['h'] ?? '')) : '';
                if ($na || ($w !== '' && $h !== '')) {
                    continue;
                }

                return __(':label requires size or NA for every size row.', ['label' => $label]);
            }
        }

        return null;
    }

    /**
     * @return array<string, array{w?: string, h?: string, na?: bool, text?: string}>
     */
    private function parseSizeMatrixValue(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $rowKey => $rowRaw) {
            $row = trim((string) $rowKey);
            if ($row === '' || ! is_array($rowRaw)) {
                continue;
            }
            $entry = [];
            if (($rowRaw['na'] ?? false) === true) {
                $entry['na'] = true;
            }
            $w = trim((string) ($rowRaw['w'] ?? ''));
            $h = trim((string) ($rowRaw['h'] ?? ''));
            $text = trim((string) ($rowRaw['text'] ?? ''));
            if ($w !== '') {
                $entry['w'] = $w;
            }
            if ($h !== '') {
                $entry['h'] = $h;
            }
            if ($text !== '') {
                $entry['text'] = $text;
            }
            if ($entry !== []) {
                $out[$row] = $entry;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function validateChecklistMatrixValue(string $value, ?array $options, string $label, bool $required): ?string
    {
        $axes = EApprovalFieldOptionsParser::checklistMatrixAxes($options);
        $rowValues = array_column($axes['rows'], 'value');
        $columnValues = array_column($axes['columns'], 'value');
        $state = $this->parseChecklistMatrixValue($value);

        foreach ($state as $rowKey => $answer) {
            if (! in_array($rowKey, $rowValues, true)) {
                return __(':label contains an invalid row.', ['label' => $label]);
            }
            foreach (array_keys($answer['cells']) as $columnKey) {
                if (! in_array($columnKey, $columnValues, true)) {
                    return __(':label contains an invalid column.', ['label' => $label]);
                }
            }
        }

        if (! $required) {
            return null;
        }

        foreach ($state as $answer) {
            if ($answer['selected'] === true) {
                return null;
            }
        }

        return __(':label requires at least one row to be selected.', ['label' => $label]);
    }

    /**
     * @return array<string, array{selected: bool, cells: array<string, string>}>
     */
    private function parseChecklistMatrixValue(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $rowKey => $rowRaw) {
            $row = trim((string) $rowKey);
            if ($row === '') {
                continue;
            }

            if (is_bool($rowRaw)) {
                $out[$row] = ['selected' => $rowRaw, 'cells' => []];

                continue;
            }

            if (! is_array($rowRaw)) {
                continue;
            }

            $selected = ($rowRaw['selected'] ?? false) === true
                || ($rowRaw['checked'] ?? false) === true
                || ($rowRaw['selected'] ?? null) === 1
                || ($rowRaw['checked'] ?? null) === 1;

            $cells = [];
            $cellsRaw = is_array($rowRaw['cells'] ?? null) ? $rowRaw['cells'] : $rowRaw;
            foreach ($cellsRaw as $columnKey => $cellValue) {
                $column = trim((string) $columnKey);
                if ($column === '' || in_array($column, ['selected', 'checked', 'cells'], true)) {
                    continue;
                }
                if (is_array($cellValue)) {
                    continue;
                }
                $cells[$column] = trim((string) $cellValue);
            }

            $out[$row] = ['selected' => $selected, 'cells' => $cells];
        }

        return $out;
    }
}
