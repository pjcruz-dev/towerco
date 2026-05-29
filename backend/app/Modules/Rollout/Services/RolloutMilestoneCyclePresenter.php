<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Data\RolloutPlaybookMilestoneResolver;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use App\Modules\Rollout\Support\WorkingDaysCalendar;
use Carbon\Carbon;

final class RolloutMilestoneCyclePresenter
{
    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
    ) {}

    /**
     * @return list<array{
     *     phase_key: string,
     *     label: string,
     *     anchor: string,
     *     target_working_days: int,
     *     target_date: string|null,
     *     status: string,
     *     variance_wd: int|null
     * }>
     */
    public function forProgram(RolloutProgram $program): array
    {
        if ($program->status === 'batch') {
            return [];
        }

        $config = TenantRolloutPlaybookConfig::query()->first();
        if ($config === null) {
            return [];
        }

        $targets = RolloutPlaybookMilestoneResolver::targetsForProjectType(
            $config->playbook_snapshot,
            (string) $program->project_type,
        );
        if ($targets === []) {
            return [];
        }

        $templateKey = RolloutPlaybookMilestoneResolver::templateKey((string) $program->project_type);
        $postDayOneStartKey = RolloutPlaybookMilestoneResolver::postDayOneStartKey(
            $config->playbook_snapshot,
            (string) $program->project_type,
        );
        $overrides = $config->day_overrides[$templateKey] ?? [];
        $calendar = $this->calendarFactory->make($program->region);
        $today = Carbon::today();
        $completedProgram = $program->status === 'completed' || $program->actual_rfi_date !== null;

        $endorsement = $program->endorsement_date ? Carbon::parse($program->endorsement_date) : null;
        $dayOne = $program->tssr_approved_date ? Carbon::parse($program->tssr_approved_date) : null;

        $rows = [];
        $postDayOne = false;
        $cursor = null;
        $anchorLabel = 'endorsement';

        foreach ($targets as $target) {
            $phaseKey = (string) ($target['phase_key'] ?? '');
            if ($phaseKey === $postDayOneStartKey) {
                $postDayOne = true;
                $cursor = $dayOne?->copy();
                $anchorLabel = 'day_one';
            }

            if (! $postDayOne) {
                if ($cursor === null && $endorsement !== null) {
                    $cursor = $endorsement->copy();
                }
            } elseif ($cursor === null && $dayOne !== null) {
                $cursor = $dayOne->copy();
            }

            $segmentDays = $this->segmentWorkingDays($target, $overrides, $phaseKey);
            $targetDate = null;

            if ($cursor !== null && $segmentDays > 0) {
                $targetDate = $calendar->addWorkingDays($cursor, $segmentDays);
                $cursor = $targetDate->copy();
            }

            $status = $this->resolveStatus(
                $completedProgram,
                $targetDate,
                $today,
                $calendar,
                $postDayOne ? $dayOne : $endorsement,
            );

            $variance = null;
            if ($targetDate !== null && $today->greaterThan($targetDate)) {
                $variance = $calendar->workingDaysBetween($targetDate, $today);
            }

            $rows[] = [
                'phase_key' => $phaseKey,
                'label' => (string) ($target['label'] ?? $phaseKey),
                'anchor' => $anchorLabel,
                'target_working_days' => $segmentDays,
                'target_date' => $targetDate?->toDateString(),
                'status' => $status,
                'variance_wd' => $variance,
                'timeline_phase_key' => isset($target['timeline_phase_key']) ? (string) $target['timeline_phase_key'] : null,
                'is_custom' => (bool) ($target['is_custom'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $cycles
     * @return array{total: int, on_track: int, overdue: int, at_risk: int, progress_pct: int}
     */
    public function summarize(array $cycles): array
    {
        $total = count($cycles);
        if ($total === 0) {
            return [
                'total' => 0,
                'on_track' => 0,
                'overdue' => 0,
                'at_risk' => 0,
                'progress_pct' => 0,
            ];
        }

        $overdue = 0;
        $atRisk = 0;
        $completed = 0;

        foreach ($cycles as $cycle) {
            match ($cycle['status']) {
                'overdue' => $overdue++,
                'at_risk' => $atRisk++,
                'completed' => $completed++,
                default => null,
            };
        }

        $onTrack = $total - $overdue - $atRisk - $completed;

        return [
            'total' => $total,
            'on_track' => max(0, $onTrack),
            'overdue' => $overdue,
            'at_risk' => $atRisk,
            'progress_pct' => (int) round(($completed / $total) * 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $overrides
     */
    private function segmentWorkingDays(array $target, array $overrides, string $phaseKey): int
    {
        $timelinePhaseKey = isset($target['timeline_phase_key']) ? (string) $target['timeline_phase_key'] : null;

        if ($timelinePhaseKey !== null && isset($overrides[$timelinePhaseKey]) && is_array($overrides[$timelinePhaseKey])) {
            $timelineOverride = $overrides[$timelinePhaseKey];
            if (isset($timelineOverride['working_day_end'])) {
                $timelineSpan = max(0, (int) $timelineOverride['working_day_end']);
                $baseSpan = max(0, (int) ($target['target_working_days'] ?? 0));

                return $baseSpan > 0 ? $baseSpan : $timelineSpan;
            }
        }

        $override = $overrides[$phaseKey] ?? null;

        if (is_array($override)) {
            if (isset($override['target_working_days'])) {
                return (int) $override['target_working_days'];
            }
            if (isset($override['working_day_end'])) {
                return (int) $override['working_day_end'];
            }
        }

        return (int) ($target['target_working_days'] ?? 0);
    }

    private function resolveStatus(
        bool $completedProgram,
        ?Carbon $targetDate,
        Carbon $today,
        WorkingDaysCalendar $calendar,
        ?Carbon $anchorDate,
    ): string {
        if ($completedProgram) {
            return 'completed';
        }

        if ($anchorDate === null || $targetDate === null) {
            return 'pending';
        }

        if ($today->greaterThan($targetDate)) {
            return 'overdue';
        }

        $remaining = $calendar->workingDaysBetween($today, $targetDate);
        if ($remaining >= 0 && $remaining <= 3) {
            return 'at_risk';
        }

        if ($today->greaterThanOrEqualTo($anchorDate)) {
            return 'active';
        }

        return 'pending';
    }
}
