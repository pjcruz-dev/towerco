<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use Illuminate\Validation\ValidationException;

final class EApprovalFormWorkspaceSupport
{
    public const ISO_FORM_FAMILY = 'iso_document_control';

    public const ISO_WORKSPACE_SLUG = 'iso-approval';

    /**
     * @return array<string, mixed>|null
     */
    public static function configFromForm(EApprovalForm $form): ?array
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $workspace = $metadata['workspace'] ?? null;

        if (! is_array($workspace) || ($workspace['enabled'] ?? false) !== true) {
            return null;
        }

        $slug = trim((string) ($workspace['slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        return self::withDashboard($form, self::normalizeWorkspace($workspace, $slug));
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>
     */
    private static function withDashboard(EApprovalForm $form, array $workspace): array
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $rawWorkspace = is_array($metadata['workspace'] ?? null) ? $metadata['workspace'] : [];
        $workspace['dashboard'] = EApprovalFormWorkspaceDashboardSupport::normalizeDashboard(
            is_array($rawWorkspace['dashboard'] ?? null) ? $rawWorkspace['dashboard'] : null,
            $form,
        );
        $workspace['acl'] = EApprovalFormWorkspaceAccessSupport::normalizeAcl(
            is_array($rawWorkspace['acl'] ?? null) ? $rawWorkspace['acl'] : null,
        );
        $workspace['forms'] = EApprovalFormWorkspaceAccessSupport::normalizeFormsGroup(
            is_array($rawWorkspace['forms'] ?? null) ? $rawWorkspace['forms'] : null,
            (string) $form->id,
        );

        return $workspace;
    }

    /**
     * @return array<string, mixed>
     */
    public static function isoPilotDefaults(?string $formName = null): array
    {
        $workspace = self::normalizeWorkspace([
            'enabled' => true,
            'slug' => self::ISO_WORKSPACE_SLUG,
            'title' => $formName !== null && trim($formName) !== '' ? trim($formName) : 'ISO Document Control',
            'description' => 'Controlled document requests, revisions, and approval tracking.',
            'default_list_scope' => 'own',
            'visibility' => 'workspace_all',
            'nav' => [
                'show_in_sidebar' => true,
                'section' => 'Operate',
            ],
            'actions' => [
                'new_request_mode' => 'focused',
                'show_export' => false,
            ],
        ], self::ISO_WORKSPACE_SLUG);

        $workspace['dashboard'] = EApprovalFormWorkspaceDashboardSupport::defaultDashboard(null);

        return $workspace;
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>
     */
    private static function normalizeWorkspace(array $workspace, string $slug): array
    {
        $nav = is_array($workspace['nav'] ?? null) ? $workspace['nav'] : [];
        $actions = is_array($workspace['actions'] ?? null) ? $workspace['actions'] : [];
        $visibility = trim((string) ($workspace['visibility'] ?? 'own'));
        if (! in_array($visibility, ['own', 'approver', 'workspace_all', 'tenant_all'], true)) {
            $visibility = 'own';
        }

        $defaultScope = trim((string) ($workspace['default_list_scope'] ?? 'own'));

        return [
            'enabled' => true,
            'slug' => $slug,
            'title' => trim((string) ($workspace['title'] ?? '')) ?: null,
            'description' => trim((string) ($workspace['description'] ?? '')) ?: null,
            'default_list_scope' => $defaultScope === 'approver' ? 'approver' : 'own',
            'visibility' => $visibility,
            'nav' => [
                'show_in_sidebar' => ($nav['show_in_sidebar'] ?? true) !== false,
                'section' => trim((string) ($nav['section'] ?? 'Operate')) ?: 'Operate',
            ],
            'actions' => [
                'new_request_mode' => trim((string) ($actions['new_request_mode'] ?? 'focused')) === 'standard'
                    ? 'standard'
                    : 'focused',
                'show_export' => ($actions['show_export'] ?? false) === true,
            ],
        ];
    }

    public static function assertUniqueSlug(?array $metadata, ?string $excludeFormId = null): void
    {
        if (! is_array($metadata)) {
            return;
        }

        $workspace = $metadata['workspace'] ?? null;
        if (! is_array($workspace) || ($workspace['enabled'] ?? false) !== true) {
            return;
        }

        $slug = strtolower(trim((string) ($workspace['slug'] ?? '')));
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages([
                'metadata_json.workspace.slug' => [__('Workspace URL slug must use lowercase letters, numbers, and hyphens.')],
            ]);
        }

        foreach (EApprovalForm::query()->where('status', 'published')->get() as $candidate) {
            if ($excludeFormId !== null && (string) $candidate->id === $excludeFormId) {
                continue;
            }

            $config = self::configFromForm($candidate);
            if ($config !== null && strtolower((string) ($config['slug'] ?? '')) === $slug) {
                throw ValidationException::withMessages([
                    'metadata_json.workspace.slug' => [__('Another published form already uses this workspace slug.')],
                ]);
            }
        }
    }

    public static function assertValidWorkspaceMetadata(?array $metadata, ?string $primaryFormId = null): void
    {
        if (! is_array($metadata)) {
            return;
        }

        $workspace = $metadata['workspace'] ?? null;
        if (! is_array($workspace) || ($workspace['enabled'] ?? false) !== true) {
            return;
        }

        $forms = is_array($workspace['forms'] ?? null) ? $workspace['forms'] : [];
        foreach ($forms['linked_form_ids'] ?? [] as $linkedId) {
            $id = trim((string) $linkedId);
            if ($id === '' || ($primaryFormId !== null && $id === $primaryFormId)) {
                continue;
            }

            $linked = EApprovalForm::query()->find($id);
            if ($linked === null) {
                throw ValidationException::withMessages([
                    'metadata_json.workspace.forms.linked_form_ids' => [__('Linked workspace form was not found.')],
                ]);
            }

            if ($linked->status !== 'published') {
                throw ValidationException::withMessages([
                    'metadata_json.workspace.forms.linked_form_ids' => [__('Linked workspace forms must be published.')],
                ]);
            }

            $linkedWorkspace = self::configFromForm($linked);
            if ($linkedWorkspace !== null) {
                throw ValidationException::withMessages([
                    'metadata_json.workspace.forms.linked_form_ids' => [__('A linked form already owns its own workspace. Disable it first.')],
                ]);
            }
        }
    }
}
