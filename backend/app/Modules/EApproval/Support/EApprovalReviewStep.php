<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalWorkflowStep;

final class EApprovalReviewStep
{
    public const DUTY = 'review';

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function isReviewDefinition(array $definition): bool
    {
        if (($definition['duty'] ?? null) === self::DUTY) {
            return true;
        }

        $condition = $definition['condition'] ?? null;

        return is_array($condition) && ($condition['duty'] ?? null) === self::DUTY;
    }

    public static function isReviewStep(?EApprovalWorkflowStep $step): bool
    {
        $condition = $step?->condition;

        return is_array($condition) && ($condition['duty'] ?? null) === self::DUTY;
    }
}
