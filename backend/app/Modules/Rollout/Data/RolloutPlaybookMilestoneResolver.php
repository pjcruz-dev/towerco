<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Resolves milestone cycle targets from playbook snapshots (keyed or legacy flat).
 */
final class RolloutPlaybookMilestoneResolver
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    public static function targetsForProjectType(array $snapshot, string $projectType): array
    {
        if (self::shouldDeriveFromTimeline($snapshot)) {
            return RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, $projectType);
        }

        return self::storedTargetsForProjectType($snapshot, $projectType);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    public static function storedTargetsForProjectType(array $snapshot, string $projectType): array
    {
        $templateKey = self::templateKey($projectType);
        $raw = $snapshot['milestone_cycle_targets'] ?? [];

        if (self::isKeyedStructure($raw)) {
            return $raw[$templateKey] ?? [];
        }

        return $templateKey === 'bts' ? $raw : [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function postDayOneStartKey(array $snapshot, string $projectType): string
    {
        if (self::shouldDeriveFromTimeline($snapshot)) {
            return RolloutPlaybookMilestoneDeriver::postDayOneStartKey($snapshot, $projectType);
        }

        return self::legacyPostDayOneStartKey($projectType);
    }

    public static function legacyPostDayOneStartKey(string $projectType): string
    {
        return match (self::templateKey($projectType)) {
            'colocation' => 'site_license',
            default => 'moc_securing',
        };
    }

    public static function templateKey(string $projectType): string
    {
        return match (strtolower($projectType)) {
            'rtb' => 'rtb',
            'colocation', 'colo' => 'colocation',
            default => 'bts',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function shouldDeriveFromTimeline(array $snapshot): bool
    {
        if (($snapshot['milestone_derived_from_timeline'] ?? false) === true) {
            return true;
        }

        if (isset($snapshot['policy_bundle_code']) && is_string($snapshot['policy_bundle_code'])) {
            return true;
        }

        return isset($snapshot['timeline_templates']) && is_array($snapshot['timeline_templates']);
    }

    /**
     * @param  array<mixed, mixed>  $raw
     */
    private static function isKeyedStructure(array $raw): bool
    {
        return isset($raw['bts']) || isset($raw['rtb']) || isset($raw['colocation']);
    }
}
