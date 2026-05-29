<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Derives milestone cycle rows from effective timeline_templates (single source of truth).
 */
final class RolloutPlaybookMilestoneDeriver
{
    /**
     * Timeline phase → ordered milestone phase_keys (fine-grained PMO checkpoints).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const TIMELINE_MILESTONE_GROUPS = [
        'bts' => [
            'endorsement' => ['endorsement_to_hunting'],
            'site_hunting' => ['site_hunting', 'pre_assessment'],
            'tssr_creation' => ['tssr_creation'],
            'tssr_mno_approval' => ['tssr_mno_approval'],
            'moc_col' => ['moc_securing', 'col_social'],
            'pre_construction' => ['pre_construction', 'ddd', 'boq'],
            'permitting' => ['permit_prep', 'locational_clearance', 'building_permit'],
            'skom' => ['skom'],
            'construction' => ['construction', 'energization', 'rfti_submission', 'site_license', 'billing'],
        ],
        'rtb' => [
            'endorsement' => ['endorsement_to_hunting'],
            'site_hunting' => ['site_hunting', 'pre_assessment'],
            'tssr_creation' => ['tssr_creation'],
            'tssr_mno_approval' => ['tssr_mno_approval'],
            'moc_col' => ['moc_securing', 'col_social'],
            'pre_construction' => ['pre_construction', 'ddd', 'boq'],
            'permitting' => ['permit_prep', 'locational_clearance', 'building_permit'],
            'skom' => ['skom'],
            'construction' => ['construction', 'energization', 'rfti_submission', 'site_license', 'billing'],
        ],
        'colocation' => [
            'site_license' => ['site_license'],
            'implementation' => ['implementation', 'billing'],
        ],
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    public static function deriveForProjectType(array $snapshot, string $projectType): array
    {
        $templateKey = RolloutPlaybookMilestoneResolver::templateKey($projectType);
        $timeline = $snapshot['timeline_templates'][$templateKey] ?? [];

        if ($timeline === []) {
            return [];
        }

        $canonical = RolloutPlaybookMilestoneResolver::storedTargetsForProjectType($snapshot, $projectType);
        $canonicalByKey = [];
        foreach ($canonical as $row) {
            $key = (string) ($row['phase_key'] ?? '');
            if ($key !== '') {
                $canonicalByKey[$key] = $row;
            }
        }

        $groups = self::TIMELINE_MILESTONE_GROUPS[$templateKey] ?? [];
        $derived = [];
        $deliveryWorkingDays = (int) ($snapshot['delivery_periods'][$templateKey]['working_days'] ?? 0);

        foreach ($timeline as $phase) {
            if (! is_array($phase)) {
                continue;
            }

            $timelineKey = (string) ($phase['phase_key'] ?? '');
            if ($timelineKey === '') {
                continue;
            }

            $span = self::phaseWorkingDaySpan($phase);

            if ((bool) ($phase['is_custom'] ?? false)) {
                $derived[] = [
                    'phase_key' => $timelineKey,
                    'label' => (string) ($phase['label'] ?? $timelineKey),
                    'target_working_days' => $span,
                    'timeline_phase_key' => $timelineKey,
                    'is_custom' => true,
                ];

                continue;
            }

            $segmentKeys = $groups[$timelineKey] ?? [$timelineKey];

            if ($timelineKey === 'endorsement' && $span === 0) {
                $derived[] = [
                    'phase_key' => 'endorsement_to_hunting',
                    'label' => (string) ($canonicalByKey['endorsement_to_hunting']['label'] ?? 'Endorsement → Site Hunting Start'),
                    'target_working_days' => 1,
                    'timeline_phase_key' => $timelineKey,
                ];

                continue;
            }

            if ($templateKey === 'colocation' && $timelineKey === 'site_license') {
                $derived[] = [
                    'phase_key' => 'site_license',
                    'label' => (string) ($canonicalByKey['site_license']['label'] ?? 'Site License Execution'),
                    'target_working_days' => max(1, (int) ($canonicalByKey['site_license']['target_working_days'] ?? 1)),
                    'timeline_phase_key' => $timelineKey,
                ];

                continue;
            }

            if ($templateKey === 'colocation' && $timelineKey === 'implementation' && $deliveryWorkingDays > 0) {
                $dayOneMilestoneDays = max(1, (int) ($canonicalByKey['site_license']['target_working_days'] ?? 1));
                $span = max(0, $deliveryWorkingDays - $dayOneMilestoneDays);
            }

            $weights = [];
            foreach ($segmentKeys as $segmentKey) {
                $weights[$segmentKey] = max(1, (int) ($canonicalByKey[$segmentKey]['target_working_days'] ?? 1));
            }

            $scaled = self::scaleWeightsToTotal($weights, max(0, $span));

            foreach ($segmentKeys as $segmentKey) {
                $derived[] = [
                    'phase_key' => $segmentKey,
                    'label' => (string) ($canonicalByKey[$segmentKey]['label'] ?? $segmentKey),
                    'target_working_days' => $scaled[$segmentKey] ?? 0,
                    'timeline_phase_key' => $timelineKey,
                ];
            }
        }

        return $derived;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function postDayOneStartKey(array $snapshot, string $projectType): string
    {
        $templateKey = RolloutPlaybookMilestoneResolver::templateKey($projectType);
        $timeline = $snapshot['timeline_templates'][$templateKey] ?? [];
        $groups = self::TIMELINE_MILESTONE_GROUPS[$templateKey] ?? [];

        foreach ($timeline as $phase) {
            if (! is_array($phase)) {
                continue;
            }

            if (($phase['anchor'] ?? '') !== 'tssr_approved') {
                continue;
            }

            $timelineKey = (string) ($phase['phase_key'] ?? '');

            if ((bool) ($phase['is_custom'] ?? false)) {
                return $timelineKey;
            }

            $segmentKeys = $groups[$timelineKey] ?? [$timelineKey];

            return $segmentKeys[0];
        }

        return RolloutPlaybookMilestoneResolver::legacyPostDayOneStartKey($projectType);
    }

    /**
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    private static function scaleWeightsToTotal(array $weights, int $total): array
    {
        if ($weights === []) {
            return [];
        }

        if ($total <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $weightSum = array_sum($weights);
        if ($weightSum <= 0) {
            $each = (int) floor($total / count($weights));

            return array_fill_keys(array_keys($weights), $each);
        }

        $scaled = [];
        $assigned = 0;
        $keys = array_keys($weights);
        $lastKey = $keys[array_key_last($keys)];

        foreach ($weights as $key => $weight) {
            if ($key === $lastKey) {
                $scaled[$key] = max(0, $total - $assigned);
            } else {
                $portion = (int) round($total * ($weight / $weightSum));
                $scaled[$key] = $portion;
                $assigned += $portion;
            }
        }

        return $scaled;
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private static function phaseWorkingDaySpan(array $phase): int
    {
        if (array_key_exists('counts_toward_sla', $phase) && ! (bool) $phase['counts_toward_sla']) {
            // Still show operational span on milestone grid.
        }

        $start = (int) ($phase['working_day_start'] ?? 0);
        $end = (int) ($phase['working_day_end'] ?? 0);

        return max(0, $end - $start + 1);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, list<array<string, mixed>>>
     */
    public static function deriveAllTemplates(array $snapshot): array
    {
        $result = [];

        foreach (['bts', 'rtb', 'colocation'] as $templateKey) {
            if (! isset($snapshot['timeline_templates'][$templateKey])) {
                continue;
            }

            $result[$templateKey] = self::deriveForProjectType($snapshot, $templateKey);
        }

        return $result;
    }
}
