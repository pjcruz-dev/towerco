<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;

final class EApprovalFormWorkflowRulesSupport
{
    public const MODE_SIMPLE = 'simple';

    public const MODE_RULES = 'rules';

    public static function usesRulesMode(EApprovalForm $form): bool
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

        return ($metadata['workflow_mode'] ?? self::MODE_SIMPLE) === self::MODE_RULES;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rulesFromForm(EApprovalForm $form): array
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $rules = $metadata['workflow_rules'] ?? [];

        if (! is_array($rules)) {
            return [];
        }

        $normalized = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $steps = is_array($rule['steps'] ?? null) ? $rule['steps'] : [];
            if ($steps === []) {
                continue;
            }

            $normalized[] = $rule;
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int => (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0),
        );

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultStepsFromForm(EApprovalForm $form): array
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $steps = $metadata['workflow_default_steps'] ?? [];

        return is_array($steps) ? array_values(array_filter($steps, is_array(...))) : [];
    }

    public static function hasPublishableRulesConfiguration(EApprovalForm $form): bool
    {
        if (! self::usesRulesMode($form)) {
            return false;
        }

        if (self::rulesFromForm($form) !== []) {
            return true;
        }

        return self::defaultStepsFromForm($form) !== [];
    }
}
