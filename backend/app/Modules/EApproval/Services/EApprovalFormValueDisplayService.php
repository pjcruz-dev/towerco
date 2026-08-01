<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use App\Modules\EApproval\Support\EApprovalUserListValueParser;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EApprovalFormValueDisplayService
{
    private const FORMER_USER_LABEL = 'Former user';

    /**
     * @param  Collection<int, EApprovalFormValue>  $values
     * @return list<array{
     *     field_id: string,
     *     field_name: string|null,
     *     field_type: string|null,
     *     label: string|null,
     *     value: string|null,
     *     display_value: string|null,
     *     display_subtitle: string|null
     * }>
     */
    public function mapForApi(Collection $values): array
    {
        $usersById = $this->approverUsersById($values);

        return $values
            ->map(fn (EApprovalFormValue $v): array => $this->toRow($v, $usersById))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, TenantUser>  $usersById
     */
    public function resolveDisplayValue(?string $fieldType, ?string $rawValue, Collection $usersById, ?array $fieldOptions = null): ?string
    {
        $display = match ($fieldType) {
            'approver' => $this->resolveApproverDisplay($rawValue, $usersById),
            'approver_list' => $this->resolveApproverListDisplay($rawValue, $usersById),
            'select', 'radio' => EApprovalFieldOptionsParser::choiceLabel($fieldOptions, $rawValue),
            'checkbox' => $this->resolveCheckboxDisplay($rawValue, $fieldOptions),
            'matrix' => $this->resolveMatrixDisplay($rawValue, $fieldOptions),
            'size_matrix' => $this->resolveSizeMatrixDisplay($rawValue, $fieldOptions),
            'checklist_matrix' => $this->resolveChecklistMatrixDisplay($rawValue, $fieldOptions),
            'currency' => $this->resolveCurrencyDisplay($rawValue),
            'date' => $this->resolveDateDisplay($rawValue),
            'date_range' => $this->resolveDateRangeDisplay($rawValue),
            'grid' => $this->resolveGridDisplay($fieldOptions, $rawValue),
            'camera' => $this->resolveCameraDisplay($rawValue),
            default => $rawValue,
        };

        return $this->maskOpaqueIdentifier($display, $rawValue, $fieldType);
    }

    /**
     * @param  Collection<string, TenantUser>  $usersById
     */
    public function resolveDisplaySubtitle(?string $fieldType, ?string $rawValue, Collection $usersById): ?string
    {
        if ($fieldType !== 'approver' || $rawValue === null || $rawValue === '') {
            return null;
        }

        /** @var TenantUser|null $user */
        $user = $usersById->get($rawValue);

        if ($user === null) {
            return null;
        }

        if (! $user->is_active) {
            $name = trim((string) $user->name);

            return $name !== '' ? $name : $user->email;
        }

        return $user->email;
    }

    /**
     * Includes inactive users so historical submissions stay readable.
     *
     * @param  Collection<int, EApprovalFormValue>  $values
     * @return Collection<string, TenantUser>
     */
    public function approverUsersById(Collection $values): Collection
    {
        $approverIds = [];
        foreach ($values as $v) {
            $type = (string) ($v->field?->type ?? '');
            if ($type === 'approver') {
                $id = trim((string) ($v->value ?? ''));
                if ($id !== '') {
                    $approverIds[] = $id;
                }
            } elseif ($type === 'approver_list') {
                foreach (EApprovalUserListValueParser::parse($v->value) as $id) {
                    $approverIds[] = $id;
                }
            }
        }

        $approverIds = array_values(array_unique(array_filter($approverIds)));

        if ($approverIds === []) {
            return collect();
        }

        return TenantUser::query()
            ->whereIn('id', $approverIds)
            ->get(['id', 'name', 'email', 'is_active'])
            ->keyBy(static fn (TenantUser $user): string => (string) $user->id);
    }

    /**
     * @param  Collection<string, TenantUser>  $usersById
     * @return array{
     *     field_id: string,
     *     field_name: string|null,
     *     field_type: string|null,
     *     label: string|null,
     *     value: string|null,
     *     display_value: string|null,
     *     display_subtitle: string|null
     * }
     */
    private function toRow(EApprovalFormValue $v, Collection $usersById): array
    {
        $type = $v->field?->type;
        $raw = $v->value;
        $options = $v->field?->options;

        return [
            'field_id' => (string) $v->field_id,
            'field_name' => $v->field?->name,
            'field_type' => $type,
            'label' => $v->field?->label,
            'value' => $raw,
            'display_value' => $this->resolveDisplayValue($type, $raw, $usersById, is_array($options) ? $options : null),
            'display_subtitle' => $this->resolveDisplaySubtitle($type, $raw, $usersById),
        ];
    }

    /**
     * @param  Collection<string, TenantUser>  $usersById
     */
    private function resolveApproverDisplay(?string $rawValue, Collection $usersById): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return $rawValue;
        }

        /** @var TenantUser|null $user */
        $user = $usersById->get($rawValue);

        if ($user === null || ! $user->is_active) {
            return self::FORMER_USER_LABEL;
        }

        return $user->name;
    }

    /**
     * @param  Collection<string, TenantUser>  $usersById
     */
    private function resolveApproverListDisplay(?string $rawValue, Collection $usersById): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        $ids = EApprovalUserListValueParser::parse($rawValue);
        if ($ids === []) {
            return '—';
        }

        $labels = [];
        foreach ($ids as $id) {
            /** @var TenantUser|null $user */
            $user = $usersById->get($id);
            if ($user === null || ! $user->is_active) {
                $labels[] = self::FORMER_USER_LABEL;

                continue;
            }
            $labels[] = (string) $user->name;
        }

        return implode(', ', $labels);
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function resolveGridDisplay(?array $options, ?string $rawValue): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        $columns = EApprovalFieldOptionsParser::gridColumns($options);
        $decoded = json_decode($rawValue, true);
        if (! is_array($decoded)) {
            return $rawValue;
        }

        $rows = array_is_list($decoded) ? $decoded : ($decoded['rows'] ?? null);
        if (! is_array($rows) || $rows === []) {
            return '—';
        }

        $lines = [];
        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells = [];
            foreach ($columns as $index => $columnLabel) {
                $key = (string) $index;
                $cell = trim((string) ($row[$key] ?? $row[$columnLabel] ?? ''));
                if ($cell !== '') {
                    $cells[] = $columnLabel.': '.$cell;
                }
            }
            if ($cells !== []) {
                $lines[] = 'Row '.((int) $rowIndex + 1).': '.implode('; ', $cells);
            }
        }

        return $lines !== [] ? implode("\n", $lines) : '—';
    }

    private function resolveCheckboxDisplay(?string $rawValue, ?array $fieldOptions = null): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return $rawValue;
        }

        $normalized = strtolower(trim($rawValue));
        if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
            return 'Yes';
        }
        if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
            return 'No';
        }

        $trimmed = trim($rawValue);
        $selected = [];
        $companions = [];

        if (str_starts_with($trimmed, '{')) {
            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }

            if (is_array($decoded) && is_array($decoded['selected'] ?? null)) {
                $selected = array_values(array_filter(array_map(
                    static fn (mixed $part): string => trim((string) $part),
                    $decoded['selected'],
                ), static fn (string $part): bool => $part !== ''));
                $companions = is_array($decoded['companions'] ?? null) ? $decoded['companions'] : [];
            }
        }

        if ($selected === []) {
            $selected = array_values(array_filter(array_map('trim', explode(',', $rawValue)), static fn (string $part): bool => $part !== ''));
        }

        if ($selected === []) {
            return $rawValue;
        }

        $choicesByValue = [];
        foreach (EApprovalFieldOptionsParser::selectChoices($fieldOptions) as $choice) {
            $choicesByValue[$choice['value']] = $choice;
        }

        $labels = [];
        foreach ($selected as $part) {
            $choice = $choicesByValue[$part] ?? null;
            $label = is_array($choice)
                ? ((string) ($choice['label'] ?? '') !== '' ? (string) $choice['label'] : $part)
                : (EApprovalFieldOptionsParser::choiceLabel($fieldOptions, $part) ?? $part);

            $inputs = is_array($choice['inputs'] ?? null) ? $choice['inputs'] : [];
            $companionParts = [];
            foreach ($inputs as $input) {
                $key = (string) ($input['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $companionValue = trim((string) ($companions[$part][$key] ?? ''));
                if ($companionValue === '') {
                    continue;
                }
                $suffix = trim((string) ($input['suffix'] ?? ''));
                if (($input['type'] ?? 'text') === 'size') {
                    $sizeLabel = $this->formatCompanionSizeDisplay($companionValue);
                    if ($sizeLabel === '') {
                        continue;
                    }
                    $companionParts[] = $suffix !== '' ? $sizeLabel.' '.$suffix : $sizeLabel;
                    continue;
                }
                $companionParts[] = $suffix !== '' ? $companionValue.' '.$suffix : $companionValue;
            }

            $labels[] = $companionParts !== []
                ? $label.' — '.implode(', ', $companionParts)
                : $label;
        }

        return implode('; ', $labels);
    }

    private function resolveMatrixDisplay(?string $rawValue, ?array $fieldOptions = null): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        $trimmed = trim($rawValue);
        if (! str_starts_with($trimmed, '{')) {
            return $rawValue;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $rawValue;
        }

        if (! is_array($decoded) || array_is_list($decoded) || $decoded === []) {
            return $rawValue;
        }

        $axes = EApprovalFieldOptionsParser::matrixAxes($fieldOptions);
        $rowLabels = [];
        foreach ($axes['rows'] as $row) {
            $rowLabels[$row['value']] = $row['label'] !== '' ? $row['label'] : $row['value'];
        }
        $columnLabels = [];
        foreach ($axes['columns'] as $column) {
            $columnLabels[$column['value']] = $column['label'] !== '' ? $column['label'] : $column['value'];
        }

        $answers = [];
        $notes = [];
        if (isset($decoded['answers']) && is_array($decoded['answers'])) {
            foreach ($decoded['answers'] as $rowKey => $columnValue) {
                $answers[trim((string) $rowKey)] = trim((string) $columnValue);
            }
            if (isset($decoded['notes']) && is_array($decoded['notes'])) {
                foreach ($decoded['notes'] as $rowKey => $noteValue) {
                    $notes[trim((string) $rowKey)] = trim((string) $noteValue);
                }
            }
        } else {
            foreach ($decoded as $rowKey => $columnValue) {
                $row = trim((string) $rowKey);
                if ($row === '') {
                    continue;
                }
                if (is_array($columnValue)) {
                    $answers[$row] = trim((string) ($columnValue['value'] ?? $columnValue['v'] ?? ''));
                    $note = trim((string) ($columnValue['note'] ?? $columnValue['n'] ?? ''));
                    if ($note !== '') {
                        $notes[$row] = $note;
                    }
                } else {
                    $answers[$row] = trim((string) $columnValue);
                }
            }
        }

        $lines = [];
        foreach ($axes['rows'] as $row) {
            $selected = trim((string) ($answers[$row['value']] ?? ''));
            if ($selected === '') {
                continue;
            }
            $rowLabel = $rowLabels[$row['value']] ?? $row['value'];
            $columnLabel = $columnLabels[$selected] ?? $selected;
            $note = trim((string) ($notes[$row['value']] ?? ''));
            $lines[] = $note !== ''
                ? $rowLabel.': '.$columnLabel.' ('.$note.')'
                : $rowLabel.': '.$columnLabel;
        }

        if ($lines === []) {
            foreach ($answers as $rowKey => $columnValue) {
                $row = trim((string) $rowKey);
                $column = trim((string) $columnValue);
                if ($row === '' || $column === '') {
                    continue;
                }
                $note = trim((string) ($notes[$row] ?? ''));
                $base = ($rowLabels[$row] ?? $row).': '.($columnLabels[$column] ?? $column);
                $lines[] = $note !== '' ? $base.' ('.$note.')' : $base;
            }
        }

        return $lines !== [] ? implode('; ', $lines) : $rawValue;
    }

    private function resolveChecklistMatrixDisplay(?string $rawValue, ?array $fieldOptions = null): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        $trimmed = trim($rawValue);
        if (! str_starts_with($trimmed, '{')) {
            return $rawValue;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $rawValue;
        }

        if (! is_array($decoded) || array_is_list($decoded) || $decoded === []) {
            return $rawValue;
        }

        $axes = EApprovalFieldOptionsParser::checklistMatrixAxes($fieldOptions);
        $rowLabels = [];
        foreach ($axes['rows'] as $row) {
            $rowLabels[$row['value']] = $row['label'] !== '' ? $row['label'] : $row['value'];
        }
        $columnLabels = [];
        foreach ($axes['columns'] as $column) {
            $columnLabels[$column['value']] = $column['label'] !== '' ? $column['label'] : $column['value'];
        }

        $lines = [];
        foreach ($axes['rows'] as $row) {
            $entry = $decoded[$row['value']] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            $selected = ($entry['selected'] ?? false) === true
                || ($entry['checked'] ?? false) === true
                || ($entry['selected'] ?? null) === 1
                || ($entry['checked'] ?? null) === 1;
            if (! $selected) {
                continue;
            }

            $cellsRaw = is_array($entry['cells'] ?? null) ? $entry['cells'] : $entry;
            $parts = [];
            foreach ($axes['columns'] as $column) {
                $cell = trim((string) ($cellsRaw[$column['value']] ?? ''));
                if ($cell === '') {
                    continue;
                }
                $displayCell = $cell;
                if (($column['type'] ?? 'text') === 'select' && is_array($column['choices'] ?? null)) {
                    foreach ($column['choices'] as $choice) {
                        if (! is_array($choice)) {
                            continue;
                        }
                        if (trim((string) ($choice['value'] ?? '')) === $cell) {
                            $label = trim((string) ($choice['label'] ?? ''));
                            $displayCell = $label !== '' ? $label : $cell;
                            break;
                        }
                    }
                }
                $parts[] = ($columnLabels[$column['value']] ?? $column['value']).': '.$displayCell;
            }

            $rowLabel = $rowLabels[$row['value']] ?? $row['value'];
            $lines[] = $parts !== [] ? $rowLabel.' — '.implode('; ', $parts) : $rowLabel;
        }

        return $lines !== [] ? implode("\n", $lines) : $rawValue;
    }

    private function resolveCurrencyDisplay(?string $rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        $trimmed = trim($rawValue);
        if ($trimmed === '') {
            return $rawValue;
        }

        $negative = str_starts_with($trimmed, '-');
        $body = preg_replace('/[^\d.]/', '', $trimmed) ?? '';
        if ($body === '' || $body === '.') {
            return $rawValue;
        }

        $dot = strpos($body, '.');
        $intPart = $dot === false ? $body : substr($body, 0, $dot);
        $decPart = $dot === false ? null : substr($body, $dot + 1);
        $intPart = ltrim($intPart, '0');
        if ($intPart === '') {
            $intPart = '0';
        }

        // Regroup from digit string (avoid float precision loss on large amounts).
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $intPart) ?? $intPart;

        if ($decPart === null || $decPart === '') {
            return ($negative ? '-' : '').$grouped;
        }

        $decPart = substr(preg_replace('/\D/', '', $decPart) ?? '', 0, 2);

        return ($negative ? '-' : '').$grouped.($decPart !== '' ? '.'.$decPart : '');
    }

    private function formatCompanionSizeDisplay(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }
        if (strtolower($trimmed) === 'na') {
            return 'NA';
        }
        if (! str_starts_with($trimmed, '{')) {
            return '';
        }
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }
        if (! is_array($decoded)) {
            return '';
        }
        if (($decoded['na'] ?? false) === true) {
            return 'NA';
        }
        $w = trim((string) ($decoded['w'] ?? ''));
        $h = trim((string) ($decoded['h'] ?? ''));
        if ($w === '' && $h === '') {
            return '';
        }

        return ($w !== '' ? $w : '—').' × '.($h !== '' ? $h : '—');
    }

    private function resolveSizeMatrixDisplay(?string $rawValue, ?array $fieldOptions = null): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        $trimmed = trim($rawValue);
        if (! str_starts_with($trimmed, '{')) {
            return $rawValue;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $rawValue;
        }

        if (! is_array($decoded) || array_is_list($decoded) || $decoded === []) {
            return $rawValue;
        }

        $rows = EApprovalFieldOptionsParser::sizeMatrixRows($fieldOptions);
        $rowLabels = [];
        foreach ($rows as $row) {
            $rowLabels[$row['value']] = $row['label'] !== '' ? $row['label'] : $row['value'];
        }

        $lines = [];
        foreach ($rows as $row) {
            $entry = $decoded[$row['value']] ?? null;
            if (! is_array($entry)) {
                continue;
            }
            $rowLabel = $rowLabels[$row['value']] ?? $row['value'];
            if (($row['input'] ?? 'size') === 'text') {
                $text = trim((string) ($entry['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $lines[] = $rowLabel.': '.$text;
                continue;
            }
            if (($entry['na'] ?? false) === true) {
                $lines[] = $rowLabel.': NA';
                continue;
            }
            $w = trim((string) ($entry['w'] ?? ''));
            $h = trim((string) ($entry['h'] ?? ''));
            if ($w === '' && $h === '') {
                continue;
            }
            $lines[] = $rowLabel.': '.($w !== '' ? $w : '—').' × '.($h !== '' ? $h : '—');
        }

        return $lines !== [] ? implode('; ', $lines) : $rawValue;
    }

    private function resolveCameraDisplay(?string $rawValue): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return null;
        }

        $names = array_values(array_filter(array_map('trim', explode(',', $rawValue))));
        $count = count($names);
        if ($count === 0) {
            return null;
        }

        return $count === 1
            ? __('1 photo')
            : __(':count photos', ['count' => $count]);
    }

    private function resolveDateDisplay(?string $rawValue): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        try {
            return Carbon::parse($rawValue)->format('Y-m-d');
        } catch (\Throwable) {
            return $rawValue;
        }
    }

    private function resolveDateRangeDisplay(?string $rawValue): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return $rawValue;
        }

        $from = '';
        $to = '';
        $trimmed = trim($rawValue);

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
                return $rawValue;
            }
        }

        if ($from === '' && $to === '') {
            return null;
        }

        $fromDisplay = $from !== '' ? $this->resolveDateDisplay($from) : '—';
        $toDisplay = $to !== '' ? $this->resolveDateDisplay($to) : '—';

        return $fromDisplay.' – '.$toDisplay;
    }

    private function maskOpaqueIdentifier(?string $display, ?string $raw, ?string $fieldType): ?string
    {
        if ($raw === null || $raw === '' || $display === null) {
            return $display;
        }

        if ($display !== $raw || ! $this->looksLikeUuid($raw)) {
            return $display;
        }

        return $fieldType === 'approver' ? self::FORMER_USER_LABEL : '—';
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            trim($value),
        );
    }
}
