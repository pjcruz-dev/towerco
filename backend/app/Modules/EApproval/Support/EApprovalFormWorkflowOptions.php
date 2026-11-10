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
     * Notes on the request details. Off until the form turns it on.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function requestCommentsEnabled(?array $metadata): bool
    {
        return (bool) (self::options($metadata)['request_comments'] ?? false);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function mandatoryTrainingEnabled(?array $metadata): bool
    {
        $node = self::trainingNode($metadata);

        return (bool) ($node['enabled'] ?? false);
    }

    /**
     * Empty means every request on the form.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function mandatoryTrainingField(?array $metadata): string
    {
        $node = self::trainingNode($metadata);
        $field = trim((string) ($node['field'] ?? ''));
        if ($field !== '') {
            return $field;
        }

        $legacy = $node['document_types'] ?? null;

        return is_array($legacy) && $legacy !== [] ? 'document_type' : '';
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return list<string>
     */
    public static function mandatoryTrainingValues(?array $metadata): array
    {
        $node = self::trainingNode($metadata);
        $types = $node['values'] ?? $node['document_types'] ?? [];
        if (! is_array($types)) {
            return [];
        }

        $clean = [];
        foreach ($types as $type) {
            $text = trim((string) $type);
            if ($text !== '') {
                $clean[] = $text;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private static function trainingNode(?array $metadata): array
    {
        $node = self::options($metadata)['mandatory_training'] ?? null;

        return is_array($node) ? $node : [];
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
