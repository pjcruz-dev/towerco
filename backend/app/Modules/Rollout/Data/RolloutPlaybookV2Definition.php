<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * TowerCo Rollout Playbook v2.0.0 — tuned cycle targets + PH holiday-aware SLA math.
 */
final class RolloutPlaybookV2Definition
{
    public const VERSION = '2.0.0';

    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        $payload = RolloutPlaybookV1Definition::payload();
        $payload['version'] = self::VERSION;
        $payload['name'] = 'TowerCo Rollout Playbook v2';
        $payload['delivery_periods']['bts']['working_days'] = 115;
        $payload['changelog'] = 'v2: BTS SLA tuned to 115 WD; RTB timeline/milestones scaled to 85 WD; site hunting window tightened; PH public holidays excluded from working-day SLA math when tenant holiday calendar is seeded.';

        $btsTimeline = self::btsTimelineV2();
        $payload['timeline_templates']['bts'] = $btsTimeline;
        $payload['timeline_templates']['rtb'] = RolloutPlaybookTemplateScaler::scalePostDayOneTimeline($btsTimeline, 85);

        $btsMilestones = array_map(static function (array $row): array {
            if ($row['phase_key'] === 'site_hunting') {
                $row['target_working_days'] = 6;
            }

            return $row;
        }, $payload['milestone_cycle_targets']['bts']);

        $payload['milestone_cycle_targets'] = [
            'bts' => $btsMilestones,
            'rtb' => RolloutPlaybookTemplateScaler::scalePostMocCycleTargets($btsMilestones, 85),
            'colocation' => $payload['milestone_cycle_targets']['colocation'],
        ];

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function btsTimelineV2(): array
    {
        $timeline = RolloutPlaybookV1Definition::payload()['timeline_templates']['bts'];

        return array_map(static function (array $phase): array {
            if ($phase['phase_key'] === 'site_hunting') {
                $phase['working_day_end'] = 7;
            }
            if ($phase['phase_key'] === 'construction') {
                $phase['working_day_end'] = 115;
            }

            return $phase;
        }, $timeline);
    }
}
