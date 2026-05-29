<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Scales BTS-shaped playbook templates to RTB SLA budgets while preserving phase order.
 */
final class RolloutPlaybookTemplateScaler
{
    /**
     * @param  list<array<string, mixed>>  $timeline
     * @return list<array<string, mixed>>
     */
    public static function scalePostDayOneTimeline(array $timeline, int $postDayOneBudget): array
    {
        $result = $timeline;
        $postIndices = [];

        foreach ($timeline as $index => $phase) {
            if (($phase['anchor'] ?? '') === 'tssr_approved') {
                $postIndices[] = $index;
            }
        }

        if ($postIndices === []) {
            return $timeline;
        }

        $durations = [];
        foreach ($postIndices as $index) {
            $start = (int) $result[$index]['working_day_start'];
            $end = (int) $result[$index]['working_day_end'];
            $durations[] = max(1, $end - $start + 1);
        }

        $totalOriginal = array_sum($durations);
        if ($totalOriginal <= 0) {
            return $timeline;
        }

        $cursor = 1;
        $remaining = $postDayOneBudget;

        foreach ($postIndices as $position => $index) {
            $isLast = $position === count($postIndices) - 1;
            if ($isLast) {
                $duration = max(1, $remaining);
            } else {
                $duration = max(1, (int) round($durations[$position] / $totalOriginal * $postDayOneBudget));
                $remaining -= $duration;
            }

            $result[$index]['working_day_start'] = $cursor;
            $result[$index]['working_day_end'] = $cursor + $duration - 1;
            $cursor += $duration;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $targets
     * @return list<array<string, mixed>>
     */
    public static function scalePostMocCycleTargets(
        array $targets,
        int $postMocBudget,
        string $postMocStartKey = 'moc_securing',
    ): array {
        $pre = [];
        $post = [];
        $inPost = false;

        foreach ($targets as $row) {
            if (($row['phase_key'] ?? '') === $postMocStartKey) {
                $inPost = true;
            }

            if ($inPost) {
                $post[] = $row;
            } else {
                $pre[] = $row;
            }
        }

        if ($post === []) {
            return $targets;
        }

        $originalPostSum = array_sum(array_map(
            static fn (array $row): int => (int) ($row['target_working_days'] ?? 0),
            $post,
        ));

        if ($originalPostSum <= 0) {
            return $targets;
        }

        $scaledPost = [];
        $remaining = $postMocBudget;
        $count = count($post);

        foreach ($post as $index => $row) {
            $isLast = $index === $count - 1;
            if ($isLast) {
                $days = max(1, $remaining);
            } else {
                $days = max(1, (int) round((int) $row['target_working_days'] / $originalPostSum * $postMocBudget));
                $remaining -= $days;
            }

            $scaledPost[] = array_merge($row, ['target_working_days' => $days]);
        }

        return array_merge($pre, $scaledPost);
    }

}
