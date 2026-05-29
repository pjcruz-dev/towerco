<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;

final class RolloutSlaRecalculationService
{
    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
        private readonly RolloutBroadcaster $broadcaster,
    ) {}

    public function recalculateProgram(RolloutProgram $program): RolloutProgram
    {
        $program->loadMissing('timelinePhases');

        if ($program->status === 'batch') {
            return $program;
        }

        $workingDays = $this->calendarFactory->make($program->region);

        if ($program->tssr_approved_date !== null && $program->sla_working_days > 0) {
            $deliveryStart = Carbon::parse($program->tssr_approved_date);
            $program->target_rfi_working_date = $workingDays
                ->addWorkingDays($deliveryStart, $program->sla_working_days)
                ->toDateString();
            $program->save();
        }

        if ($program->timelinePhases->isNotEmpty()) {
            $this->recalculatePhaseTargetDates($program, $workingDays);
        }

        $fresh = $program->fresh(['timelinePhases']);
        $this->broadcaster->rolloutUpdated($fresh, 'rollout.sla_recalculated');

        return $fresh;
    }

    public function recalculateActivePrograms(): int
    {
        $programs = RolloutProgram::query()
            ->whereNull('actual_rfi_date')
            ->whereNotIn('status', ['batch', 'completed', 'cancelled'])
            ->get();

        foreach ($programs as $program) {
            $this->recalculateProgram($program);
        }

        return $programs->count();
    }

    private function recalculatePhaseTargetDates(RolloutProgram $program, \App\Modules\Rollout\Support\WorkingDaysCalendar $workingDays): void
    {
        $endorsement = $program->endorsement_date ? Carbon::parse($program->endorsement_date) : null;
        $tssr = $program->tssr_approved_date ? Carbon::parse($program->tssr_approved_date) : null;

        foreach ($program->timelinePhases as $phase) {
            $usesTssrAnchor = $phase->anchor === 'tssr_approved';
            $anchorDate = $usesTssrAnchor ? $tssr : $endorsement;

            if ($anchorDate === null) {
                $phase->target_start_date = null;
                $phase->target_end_date = null;
                $phase->save();

                continue;
            }

            $phase->target_start_date = $workingDays->addWorkingDays($anchorDate, $phase->working_day_start);
            $phase->target_end_date = $workingDays->addWorkingDays($anchorDate, $phase->working_day_end);
            $phase->save();
        }
    }
}
