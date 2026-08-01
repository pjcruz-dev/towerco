<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalRevisionRouting
{
    public const RESTART_FROM_START = 'restart_from_start';

    public const RESUME_RETURNING_STEP = 'resume_returning_step';

    public const REASON_DEFAULT = 'default_restart';

    public const REASON_FORM_SETTING = 'form_restart_setting';

    public const REASON_FORCE_FLAG = 'approver_force_full_restart';

    public const REASON_MATERIAL_FIELDS = 'material_fields_changed';

    public const REASON_MISSING_RETURN_STEP = 'missing_return_step';

    public const REASON_STEP_CONDITION = 'return_step_condition_failed';

    public const REASON_RESUME = 'resume_returning_step';

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{
     *     routing: string,
     *     material_fields: list<string>,
     *     approver_can_force_full_restart: bool
     * }
     */
    public static function fromFormMetadata(?array $metadata): array
    {
        $raw = is_array($metadata['revision'] ?? null) ? $metadata['revision'] : [];

        $routing = (string) ($raw['routing'] ?? self::RESTART_FROM_START);
        if ($routing !== self::RESUME_RETURNING_STEP) {
            $routing = self::RESTART_FROM_START;
        }

        $material = [];
        foreach ($raw['material_fields'] ?? [] as $field) {
            if (is_string($field) && trim($field) !== '') {
                $material[] = trim($field);
            }
        }

        return [
            'routing' => $routing,
            'material_fields' => array_values(array_unique($material)),
            'approver_can_force_full_restart' => (bool) ($raw['approver_can_force_full_restart'] ?? false),
        ];
    }
}
