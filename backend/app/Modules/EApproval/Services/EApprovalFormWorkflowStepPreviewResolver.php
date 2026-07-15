<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use App\Modules\Identity\Models\TenantUser;

final class EApprovalFormWorkflowStepPreviewResolver
{
    public function __construct(
        private readonly EApprovalRoleApproverResolver $roleResolver,
        private readonly EApprovalFieldMapResolver $fieldMapResolver,
        private readonly EApprovalFormFieldChoicesResolver $fieldChoicesResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function describe(array $definition, array $values, int $stepOrder, ?EApprovalForm $form = null): array
    {
        $type = EApprovalFormPolicySupport::normalizeApproverType(
            (string) ($definition['type'] ?? $definition['approver_type'] ?? 'user'),
        );
        $approverId = isset($definition['approverId'])
            ? trim((string) $definition['approverId'])
            : trim((string) ($definition['approver_id'] ?? ''));

        $label = match ($type) {
            'manager' => __('Direct manager (Entra)'),
            'field' => $approverId !== '' ? __('Approver field: :field', ['field' => $approverId]) : __('Approver field'),
            'field_map' => $this->fieldMapLabel($definition, $values),
            'role' => $approverId !== '' ? __('Role: :role', ['role' => $approverId]) : __('Role'),
            default => $this->userLabel($approverId),
        };

        $resolvedUserId = match ($type) {
            'user' => $this->resolveUserId($approverId),
            'field' => $this->resolveUserId(trim((string) ($values[$approverId] ?? ''))),
            'field_map' => $this->resolveFieldMapUserId($definition, $values, $form),
            'role' => $approverId !== '' ? $this->roleResolver->resolveFirstApproverForRole($approverId) : null,
            default => null,
        };

        $user = $resolvedUserId !== null
            ? TenantUser::query()->where('id', $resolvedUserId)->first(['id', 'name', 'email'])
            : null;

        $sourceField = trim((string) ($definition['source_field'] ?? $definition['approverId'] ?? ''));
        $sourceValue = $sourceField !== '' ? trim((string) ($values[$sourceField] ?? '')) : '';
        $choices = $type === 'field_map' && $form !== null && $sourceField !== ''
            ? $this->fieldChoicesResolver->choicesForFieldName($form, $sourceField)
            : [];
        $matchedKey = $type === 'field_map'
            ? $this->fieldMapResolver->resolveMatchedKey(
                is_array($definition['mappings'] ?? null) ? $definition['mappings'] : [],
                $sourceValue,
                $choices,
            )
            : null;

        return [
            'step_order' => (int) ($definition['step_order'] ?? $stepOrder),
            'type' => $type,
            'label' => $label,
            'resolved_user_id' => $user?->id,
            'resolved_user_name' => $user?->name,
            'resolved_user_email' => $user?->email,
            'mapping_source_field' => $type === 'field_map' ? ($sourceField !== '' ? $sourceField : null) : null,
            'mapping_source_value' => $type === 'field_map' ? ($sourceValue !== '' ? $sourceValue : null) : null,
            'mapping_matched_key' => $matchedKey,
            'warning' => $this->warningForStep($type, $approverId, $user, $definition, $values, $form),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fieldMapLabel(array $definition, array $values): string
    {
        $sourceField = trim((string) ($definition['source_field'] ?? $definition['approverId'] ?? ''));
        $raw = trim((string) ($values[$sourceField] ?? ''));

        return $raw !== ''
            ? __('Mapped approver for :field = :value', ['field' => $sourceField, 'value' => $raw])
            : __('Mapped approver from :field', ['field' => $sourceField !== '' ? $sourceField : __('field')]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     */
    private function resolveFieldMapUserId(array $definition, array $values, ?EApprovalForm $form): ?string
    {
        $sourceField = trim((string) ($definition['source_field'] ?? $definition['approverId'] ?? ''));
        if ($sourceField === '') {
            return null;
        }

        $raw = trim((string) ($values[$sourceField] ?? ''));
        $mappings = is_array($definition['mappings'] ?? null) ? $definition['mappings'] : [];
        $default = $definition['default_approver_id'] ?? null;
        $choices = $form !== null ? $this->fieldChoicesResolver->choicesForFieldName($form, $sourceField) : [];

        return $this->fieldMapResolver->resolveApproverId(
            $mappings,
            $raw,
            is_string($default) ? $default : null,
            $choices,
        );
    }

    private function resolveUserId(string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $userId = TenantUser::query()
            ->where('id', $candidate)
            ->where('is_active', true)
            ->value('id');

        if ($userId !== null) {
            return (string) $userId;
        }

        if (str_contains($candidate, '@')) {
            $byEmail = TenantUser::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($candidate)])
                ->where('is_active', true)
                ->value('id');

            return $byEmail !== null ? (string) $byEmail : null;
        }

        return null;
    }

    private function userLabel(string $approverId): string
    {
        if ($approverId === '') {
            return __('Fixed user');
        }

        $user = TenantUser::query()->where('id', $approverId)->first(['name', 'email']);

        return $user !== null
            ? trim((string) $user->name)
            : __('Fixed user');
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     */
    private function warningForStep(
        string $type,
        string $approverId,
        ?TenantUser $user,
        array $definition,
        array $values,
        ?EApprovalForm $form = null,
    ): ?string {
        if ($type === 'manager') {
            return null;
        }

        if ($type === 'field_map') {
            return $this->resolveFieldMapUserId($definition, $values, $form) === null
                ? __('No approver mapping found for the selected field value.')
                : null;
        }

        if ($type === 'role' && $user === null) {
            return __('No active approver found for this role.');
        }

        if ($type === 'field' && $approverId !== '' && trim((string) ($values[$approverId] ?? '')) === '') {
            return __('Approver field is empty in the preview sample.');
        }

        if ($type === 'user' && $user === null) {
            return __('Fixed approver is missing or inactive.');
        }

        return null;
    }
}
