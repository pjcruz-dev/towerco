<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

final class RolloutPermitCatalog
{
    /** @var array<string, array{label: string, sort_order: int, timeline_phase_key: string}> */
    public const TYPES = [
        'moc' => ['label' => 'MOC secured', 'sort_order' => 10, 'timeline_phase_key' => 'moc_col'],
        'brgy_clearance' => ['label' => 'Barangay clearance', 'sort_order' => 20, 'timeline_phase_key' => 'permitting'],
        'locational_clearance' => ['label' => 'Locational / zoning clearance', 'sort_order' => 30, 'timeline_phase_key' => 'permitting'],
        'building_permit' => ['label' => 'Building permit', 'sort_order' => 40, 'timeline_phase_key' => 'permitting'],
        'excavation_permit' => ['label' => 'Excavation permit', 'sort_order' => 50, 'timeline_phase_key' => 'permitting'],
        'occupancy_permit' => ['label' => 'Occupancy permit', 'sort_order' => 60, 'timeline_phase_key' => 'permitting'],
        'cfei' => ['label' => 'CFEI', 'sort_order' => 70, 'timeline_phase_key' => 'permitting'],
    ];

    /**
     * @return list<string>
     */
    public static function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function label(string $permitType): string
    {
        return self::TYPES[$permitType]['label'] ?? $permitType;
    }

    public static function sortOrder(string $permitType): int
    {
        return self::TYPES[$permitType]['sort_order'] ?? 999;
    }

    public static function isValid(string $permitType): bool
    {
        return isset(self::TYPES[$permitType]);
    }

    public static function timelinePhaseKey(string $permitType): string
    {
        return self::TYPES[$permitType]['timeline_phase_key'] ?? 'permitting';
    }

    /**
     * @return list<string>
     */
    public static function permitTypesForTimelinePhase(string $phaseKey): array
    {
        $types = [];
        foreach (self::TYPES as $type => $meta) {
            if ($meta['timeline_phase_key'] === $phaseKey) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
