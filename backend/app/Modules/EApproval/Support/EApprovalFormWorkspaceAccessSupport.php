<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;

final class EApprovalFormWorkspaceAccessSupport
{
    /**
     * @return array{roles: list<string>, enforce_form_restricted_to: bool}
     */
    public static function normalizeAcl(?array $acl): array
    {
        if (! is_array($acl)) {
            return [
                'roles' => [],
                'enforce_form_restricted_to' => true,
            ];
        }

        $roles = $acl['roles'] ?? [];
        if (is_string($roles)) {
            $roles = self::parseRoleString($roles);
        }

        return [
            'roles' => self::normalizeRoleList(is_array($roles) ? $roles : []),
            'enforce_form_restricted_to' => ($acl['enforce_form_restricted_to'] ?? true) !== false,
        ];
    }

    /**
     * @return array{mode: string, linked_form_ids: list<string>}
     */
    public static function normalizeFormsGroup(?array $forms, string $primaryFormId): array
    {
        if (! is_array($forms)) {
            return [
                'mode' => 'single',
                'linked_form_ids' => [],
            ];
        }

        $linked = [];
        foreach ($forms['linked_form_ids'] ?? [] as $formId) {
            $id = trim((string) $formId);
            if ($id === '' || $id === $primaryFormId) {
                continue;
            }
            $linked[$id] = $id;
        }

        $linkedIds = array_values($linked);

        return [
            'mode' => count($linkedIds) > 0 ? 'multi' : 'single',
            'linked_form_ids' => $linkedIds,
        ];
    }

    /**
     * @return list<string>
     */
    public static function parseRoleString(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return self::normalizeRoleList(explode(',', $value));
    }

    /**
     * @param  list<string>  $roles
     */
    public static function viewerHasAnyRole(TenantUser $viewer, array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        foreach ($roles as $role) {
            if ($viewer->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    public static function viewerCanAccessWorkspace(TenantUser $viewer, array $workspace, EApprovalForm $form): bool
    {
        if ($viewer->can('e_approval:audit:view')) {
            return true;
        }

        if ($viewer->can('e_approval:forms:manage')) {
            return true;
        }

        $acl = is_array($workspace['acl'] ?? null)
            ? $workspace['acl']
            : self::normalizeAcl(null);

        if (! self::viewerHasAnyRole($viewer, $acl['roles'])) {
            return false;
        }

        if (($acl['enforce_form_restricted_to'] ?? true) === true) {
            $formRoles = self::parseRoleString($form->restricted_to);
            if ($formRoles !== [] && ! self::viewerHasAnyRole($viewer, $formRoles)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return list<string>
     */
    public static function resolveFormIds(EApprovalForm $primaryForm, array $workspace): array
    {
        $formsGroup = is_array($workspace['forms'] ?? null)
            ? $workspace['forms']
            : self::normalizeFormsGroup(null, (string) $primaryForm->id);

        $ids = [(string) $primaryForm->id];
        foreach ($formsGroup['linked_form_ids'] as $linkedId) {
            $linked = EApprovalForm::query()
                ->where('id', $linkedId)
                ->where('status', 'published')
                ->first();
            if ($linked !== null) {
                $ids[] = (string) $linked->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private static function normalizeRoleList(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $name = strtolower(trim((string) $role));
            if ($name === '') {
                continue;
            }
            $normalized[$name] = $name;
        }

        return array_values($normalized);
    }
}
