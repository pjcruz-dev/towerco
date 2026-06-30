<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Support\ControlledDocumentAccessPolicy;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentRegisterAccessService
{
    public function __construct(
        private readonly ControlledDocumentFormResolverService $forms,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return array{
     *     form_id: string|null,
     *     configured: bool,
     *     access_policy: array{
     *         viewer_roles: list<string>,
     *         full_access_roles: list<string>,
     *         full_access_permissions: list<string>,
     *         role_department_map: array<string, list<string>>
     *     }
     * }
     */
    public function payload(): array
    {
        $form = $this->resolveControlledDocumentForm();
        $policy = $this->forms->resolveAccessPolicy();

        return [
            'form_id' => $form?->id,
            'configured' => $form instanceof EApprovalForm,
            'access_policy' => $this->serializePolicy($policy),
        ];
    }

    /**
     * @param  array{
     *     viewer_roles?: list<string>|null,
     *     full_access_roles?: list<string>|null,
     *     role_department_map?: array<string, list<string>>|null
     * }  $input
     * @return array{
     *     form_id: string|null,
     *     configured: bool,
     *     access_policy: array{
     *         viewer_roles: list<string>,
     *         full_access_roles: list<string>,
     *         full_access_permissions: list<string>,
     *         role_department_map: array<string, list<string>>
     *     }
     * }
     */
    public function update(array $input, TenantUser $actor): array
    {
        $form = $this->resolveControlledDocumentForm();
        if (! $form instanceof EApprovalForm) {
            throw ValidationException::withMessages([
                'form' => ['No controlled document E-Approval form is configured. Publish a form with controlled document sync enabled first.'],
            ]);
        }

        $metadata = $this->metadataArray($form);
        $sync = $metadata['controlledDocumentSync'] ?? $metadata['controlled_document_sync'] ?? null;
        if (! is_array($sync)) {
            $sync = ['enabled' => true];
        }

        $policy = ControlledDocumentAccessPolicy::parse([
            'viewerRoles' => $input['viewer_roles'] ?? [],
            'fullAccessRoles' => $input['full_access_roles'] ?? null,
            'fullAccessPermissions' => ['documents:controlled:manage'],
            'roleDepartmentMap' => $input['role_department_map'] ?? [],
        ]);

        $sync['accessPolicy'] = [
            'viewerRoles' => $policy->viewerRoles,
            'fullAccessRoles' => $policy->fullAccessRoles,
            'fullAccessPermissions' => $policy->fullAccessPermissions,
            'roleDepartmentMap' => $policy->roleDepartmentMap,
        ];

        $metadata['controlledDocumentSync'] = $sync;
        unset($metadata['controlled_document_sync']);

        $form->metadata_json = $metadata;
        $form->save();

        $this->activity->record(
            module: 'documents',
            action: 'controlled_register.access_updated',
            summary: 'Updated controlled document register access policy',
            entityType: 'e_approval_form',
            entityId: (string) $form->id,
            entityLabel: $form->name,
            actor: $actor,
            metadata: [
                'viewer_roles' => $policy->viewerRoles,
                'full_access_roles' => $policy->fullAccessRoles,
                'role_department_map' => $policy->roleDepartmentMap,
            ],
        );

        return $this->payload();
    }

    public function resolveControlledDocumentForm(): ?EApprovalForm
    {
        return EApprovalForm::query()
            ->orderByDesc('updated_at')
            ->get()
            ->first(function (EApprovalForm $form): bool {
                $metadata = $this->metadataArray($form);

                if (($metadata['default_controlled_document_form'] ?? false) === true) {
                    return true;
                }

                if (($metadata['form_family'] ?? null) === 'iso_document_control') {
                    return true;
                }

                $sync = $metadata['controlledDocumentSync'] ?? $metadata['controlled_document_sync'] ?? null;

                return is_array($sync) && ($sync['enabled'] ?? false) === true;
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataArray(EApprovalForm $form): array
    {
        $metadata = $form->metadata_json;

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return array{
     *     viewer_roles: list<string>,
     *     full_access_roles: list<string>,
     *     full_access_permissions: list<string>,
     *     role_department_map: array<string, list<string>>
     * }
     */
    private function serializePolicy(ControlledDocumentAccessPolicy $policy): array
    {
        return [
            'viewer_roles' => $policy->viewerRoles,
            'full_access_roles' => $policy->fullAccessRoles,
            'full_access_permissions' => $policy->fullAccessPermissions,
            'role_department_map' => $policy->roleDepartmentMap,
        ];
    }
}
