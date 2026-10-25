<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Form workflow switches. Both default off.
 */
final class EApprovalFormWorkflowOptions
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function optionalApproverEnabled(?array $metadata): bool
    {
        $options = self::options($metadata);

        return (bool) ($options['optional_approver'] ?? false);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function sendApprovedFiles(?array $metadata): bool
    {
        $raw = is_array(self::options($metadata)['file_distribution'] ?? null)
            ? self::options($metadata)['file_distribution']
            : [];

        return (bool) ($raw['enabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private static function options(?array $metadata): array
    {
        $raw = is_array($metadata['workflow_options'] ?? null) ? $metadata['workflow_options'] : [];

        return $raw;
    }
}
