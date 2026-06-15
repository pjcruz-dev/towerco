<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use App\Modules\Identity\Models\TenantUser;
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
            'select', 'radio' => EApprovalFieldOptionsParser::choiceLabel($fieldOptions, $rawValue),
            'checkbox' => $this->resolveCheckboxDisplay($rawValue),
            'grid' => $this->resolveGridDisplay($fieldOptions, $rawValue),
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
        $approverIds = $values
            ->filter(static fn (EApprovalFormValue $v): bool => ($v->field?->type ?? '') === 'approver')
            ->pluck('value')
            ->filter(static fn (?string $id): bool => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();

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

    private function resolveCheckboxDisplay(?string $rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return $rawValue;
        }

        return match (strtolower($rawValue)) {
            'true', '1', 'yes', 'on' => 'Yes',
            'false', '0', 'no', 'off' => 'No',
            default => $rawValue,
        };
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
