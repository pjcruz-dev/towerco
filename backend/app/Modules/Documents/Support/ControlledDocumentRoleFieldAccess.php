<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Support\Facades\Schema;

/**
 * Field and action access for the controlled document register.
 * A missing row stays open, so an Assets or E-Forms matrix does not lock the register.
 */
final class ControlledDocumentRoleFieldAccess
{
    public const ENTITY = 'controlled_document';

    /** @var list<string> */
    public const FIELD_KEYS = [
        'document_code',
        'dcf_number',
        'title',
        'document_type',
        'department',
        'current_revision',
        'status',
        'effective_date',
        'next_review_date',
        'published_at',
        'aging',
        'has_file',
        'created_by_name',
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
     * A page hidden from the role stays hidden on that URL.
     * Overview matches only itself, so hiding it does not hide Register, Approvals, Files, or Reports.
     * A new document stays with Register. Opening a request stays with Approvals.
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
            '/documents/controlled',
            '/documents/controlled/register',
            '/documents/controlled/approvals',
            '/documents/controlled/obsolete',
            '/documents/controlled/reports',
        ];

        foreach ($targets as $target) {
            if (($fields['nav:'.$target] ?? null) !== 'hide') {
                continue;
            }
            if ($target === '/documents/controlled') {
                if ($path === '/documents/controlled' || $path === '/documents/controlled/') {
                    return true;
                }

                continue;
            }
            if ($target === '/documents/controlled/register') {
                if (
                    $path === $target
                    || str_starts_with($path, $target.'/')
                    || str_starts_with($path, '/documents/controlled/published')
                    || $path === '/documents/controlled/new'
                    || str_starts_with($path, '/documents/controlled/new/')
                ) {
                    return true;
                }

                continue;
            }
            if ($target === '/documents/controlled/approvals') {
                if (
                    $path === $target
                    || str_starts_with($path, $target.'/')
                    || str_starts_with($path, '/documents/controlled/requests')
                ) {
                    return true;
                }

                continue;
            }
            if ($path === $target || str_starts_with($path, $target.'/')) {
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
     * @param  array<string, mixed>  $matrix
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function redactIn(array $matrix, array $row, string $surface = 'table'): array
    {
        foreach (self::FIELD_KEYS as $key) {
            if (! array_key_exists($key, $row) || $this->visibleIn($matrix, $key, $surface)) {
                continue;
            }
            $row[$key] = null;
        }

        if (array_key_exists('revision_number', $row) && ! $this->visibleIn($matrix, 'current_revision', $surface)) {
            $row['revision_number'] = null;
        }
        if (array_key_exists('filename', $row) && ! $this->visibleIn($matrix, 'has_file', $surface)) {
            $row['filename'] = null;
        }
        if (array_key_exists('incoming_revision', $row) && ! $this->visibleIn($matrix, 'current_revision', $surface)) {
            $row['incoming_revision'] = null;
            $row['incoming_submission_id'] = null;
            $row['incoming_document_no'] = null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function redact(TenantUser $user, array $row, string $surface = 'table'): array
    {
        return $this->redactIn($this->matrixFor($user), $row, $surface);
    }

    /**
     * @return list<string>
     */
    public function hiddenExportColumns(TenantUser $user): array
    {
        $map = [
            'document_code' => 'document_code',
            'dcf_number' => 'dcf_number',
            'title' => 'title',
            'document_type' => 'document_type',
            'department' => 'department',
            'revision_number' => 'current_revision',
            'status' => 'status',
            'effective_date' => 'effective_date',
            'next_review_date' => 'next_review_date',
            'published_at' => 'published_at',
            'has_file' => 'has_file',
        ];
        $hidden = [];
        foreach ($map as $column => $field) {
            if (! $this->visible($user, $field, 'table')) {
                $hidden[] = $column;
            }
        }

        return $hidden;
    }
}
