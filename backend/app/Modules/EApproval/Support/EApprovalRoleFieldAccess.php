<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Support\Facades\Schema;

/**
 * Field and action access for E-Forms submissions. Same matrix Assets uses.
 * A missing row stays open, so an Assets-only matrix does not lock E-Forms.
 */
final class EApprovalRoleFieldAccess
{
    public const ENTITY = 'e_approval_submission';

    /** @var list<string> */
    private const LIST_KEYS = [
        'document_no',
        'form_name',
        'subsidiary',
        'department',
        'status',
        'current_step',
        'requestor',
    ];

    /**
     * @return array<string, mixed>
     */
    public function matrixFor(TenantUser $user): array
    {
        if ($user->hasRole(TenantRbacSystemRoles::FULL_ADMIN)) {
            return [];
        }

        if (! Schema::connection('tenant')->hasColumn('roles', 'access_matrix_json')) {
            return [];
        }

        $matrices = [];
        foreach ($user->roles as $role) {
            if (! $role instanceof TenantRole) {
                continue;
            }
            $matrices[] = $role->accessMatrix();
        }

        return RoleAccessMatrix::merge($matrices);
    }

    /**
     * @param  array<string, mixed>  $matrix
     */
    public function visibleIn(array $matrix, string $field, string $surface): bool
    {
        if ($field === '') {
            return true;
        }

        return RoleAccessMatrix::visibleOn(
            RoleAccessMatrix::fieldLevel($matrix, self::ENTITY, $field),
            $surface,
        );
    }

    public function visible(TenantUser $user, string $field, string $surface): bool
    {
        return $this->visibleIn($this->matrixFor($user), $field, $surface);
    }

    public function editable(TenantUser $user, string $field): bool
    {
        return RoleAccessMatrix::editableOnForm(
            RoleAccessMatrix::fieldLevel($this->matrixFor($user), self::ENTITY, $field),
        );
    }

    /**
     * @param  array<string, mixed>  $matrix
     */
    public function allowsIn(array $matrix, string $action): bool
    {
        $entities = $matrix['entities'] ?? null;
        if (! is_array($entities) || ! isset($entities[self::ENTITY]) || ! is_array($entities[self::ENTITY])) {
            return true;
        }

        return RoleAccessMatrix::entityAllows($matrix, self::ENTITY, $action) !== false;
    }

    public function allows(TenantUser $user, string $action): bool
    {
        return $this->allowsIn($this->matrixFor($user), $action);
    }

    /**
     * A page hidden from the role stays hidden on that URL and its records.
     * Creating a new submission stays available.
     *
     * @param  array<string, mixed>  $matrix
     */
    public function navTargetHiddenIn(array $matrix, string $path): bool
    {
        $fields = $matrix['fields'][self::ENTITY] ?? null;
        if (! is_array($fields)) {
            return false;
        }

        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];
        $targets = [
            '/e-approval',
            '/e-approval/forms',
            '/e-approval/submissions',
            '/e-approval/approvals',
            '/e-approval/reports',
        ];

        foreach ($targets as $target) {
            if (($fields['nav:'.$target] ?? null) !== 'hide') {
                continue;
            }
            if ($target === '/e-approval') {
                if ($path === '/e-approval' || $path === '/e-approval/') {
                    return true;
                }

                continue;
            }
            if ($path === $target || (str_starts_with($path, $target.'/') && ! str_starts_with($path, $target.'/new'))) {
                return true;
            }
        }

        return false;
    }

    public function navTargetHidden(TenantUser $user, string $path): bool
    {
        return $this->navTargetHiddenIn($this->matrixFor($user), $path);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function redactListRow(TenantUser $user, array $row, string $surface = 'table'): array
    {
        return $this->redactListRowIn($this->matrixFor($user), $row, $surface);
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function redactListRowIn(array $matrix, array $row, string $surface = 'table'): array
    {
        foreach (self::LIST_KEYS as $key) {
            if ($this->visibleIn($matrix, $key, $surface)) {
                continue;
            }
            $row[$key] = null;
            if ($key === 'status') {
                $row['status_label'] = null;
            }
            if ($key === 'current_step') {
                $row['workflow_steps'] = [];
                $row['publication_training'] = null;
            }
            if ($key === 'requestor') {
                $row['requestor_name'] = null;
                $row['requestor_email'] = null;
                $row['external_submitter_name'] = null;
                $row['external_submitter_email'] = null;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redactDetail(TenantUser $user, array $payload): array
    {
        $payload = $this->redactListRow($user, $payload, 'record');

        if (isset($payload['values']) && is_array($payload['values'])) {
            $payload['values'] = array_values(array_filter(
                $payload['values'],
                fn (mixed $row): bool => $this->rowFieldVisible($user, $row, 'record'),
            ));
        }

        if (isset($payload['form_fields']) && is_array($payload['form_fields'])) {
            $payload['form_fields'] = array_values(array_filter(
                $payload['form_fields'],
                function (mixed $field) use ($user): bool {
                    if (! is_array($field)) {
                        return false;
                    }
                    $type = (string) ($field['type'] ?? '');
                    if (in_array($type, ['section', 'divider', 'heading'], true)) {
                        return true;
                    }
                    $name = (string) ($field['name'] ?? '');

                    return $name === '' || $this->visible($user, $name, 'record');
                },
            ));
        }

        if (isset($payload['revision_baseline']) && is_array($payload['revision_baseline'])) {
            foreach (array_keys($payload['revision_baseline']) as $name) {
                if (! is_string($name)) {
                    continue;
                }
                if (! $this->visible($user, $name, 'record')) {
                    unset($payload['revision_baseline'][$name]);
                }
            }
        }

        if (isset($payload['attachments']) && is_array($payload['attachments'])) {
            $payload['attachments'] = array_values(array_filter(
                $payload['attachments'],
                fn (mixed $row): bool => $this->rowFieldVisible($user, $row, 'record'),
            ));
        }

        return $payload;
    }

    /**
     * @param  array<string, string|null>  $fieldValues
     * @return array<string, string|null>
     */
    public function redactFieldMap(TenantUser $user, array $fieldValues, string $surface): array
    {
        $out = [];
        foreach ($fieldValues as $name => $value) {
            if (! is_string($name)) {
                continue;
            }
            if ($this->visible($user, $name, $surface)) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * Drop values this role cannot fill in. Stored locked values are put back by the caller.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function onlyEditable(TenantUser $user, array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (! is_string($key) || ! $this->editable($user, $key)) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Map an export column key onto the field name the role matrix uses.
     * Null means the column is structural and stays in the file.
     */
    public function fieldForExportKey(string $key, ?EApprovalForm $form): ?string
    {
        if (in_array($key, ['document_no', 'form_name', 'subsidiary', 'department', 'status', 'current_step'], true)) {
            return $key;
        }
        if (in_array($key, ['requestor', 'requestor_id', 'requestor_name', 'requestor_email'], true)) {
            return 'requestor';
        }
        if (preg_match('/^field:([0-9a-f-]{36})(?::attachments)?$/i', $key, $match) === 1 && $form !== null) {
            $form->loadMissing('fields');
            $field = $form->fields->firstWhere('id', $match[1]);
            if ($field !== null && is_string($field->name) && $field->name !== '') {
                return $field->name;
            }
        }

        return null;
    }

    private function rowFieldVisible(TenantUser $user, mixed $row, string $surface): bool
    {
        if (! is_array($row)) {
            return false;
        }
        $name = (string) ($row['field_name'] ?? $row['name'] ?? '');

        return $name === '' || $this->visible($user, $name, $surface);
    }
}
