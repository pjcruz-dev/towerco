<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class RolloutGateApprovalEscalationService
{
    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
        private readonly RolloutGateApprovalNotificationDispatcher $notificationDispatcher,
    ) {}

    public function escalationWorkingDays(): int
    {
        if (! Schema::connection('tenant')->hasColumn('tenant_rollout_playbook_config', 'gate_approval_escalation_working_days')) {
            return 3;
        }

        $config = TenantRolloutPlaybookConfig::query()->first();
        $days = (int) ($config?->gate_approval_escalation_working_days ?? 3);

        return max(1, $days);
    }

    /**
     * @return list<RolloutGateApprovalRequest>
     */
    public function dueForEscalation(): array
    {
        if (! Schema::connection('tenant')->hasColumn('rollout_gate_approval_requests', 'current_step_started_at')) {
            return [];
        }

        $thresholdDays = $this->escalationWorkingDays();
        $today = Carbon::today();

        return RolloutGateApprovalRequest::query()
            ->with(['rolloutProgram', 'timelinePhase', 'requestedBy'])
            ->where('status', RolloutGateApprovalRequest::STATUS_IN_REVIEW)
            ->whereNotNull('current_step_started_at')
            ->get()
            ->filter(function (RolloutGateApprovalRequest $request) use ($thresholdDays, $today): bool {
                $program = $request->rolloutProgram;
                if ($program === null) {
                    return false;
                }

                $stepStarted = Carbon::parse($request->current_step_started_at)->startOfDay();
                $calendar = $this->calendarFactory->make($program->region);
                $waitingDays = $calendar->workingDaysBetween($stepStarted, $today);

                if ($waitingDays < $thresholdDays) {
                    return false;
                }

                if ($request->last_escalated_at !== null
                    && Carbon::parse($request->last_escalated_at)->gte($request->current_step_started_at)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    public function escalate(RolloutGateApprovalRequest $request): void
    {
        $program = $request->rolloutProgram;
        $role = $request->currentApproverRole();

        if ($program === null || $role === null) {
            return;
        }

        $this->notificationDispatcher->dispatch($request, 'escalated');

        $request->last_escalated_at = now();
        $request->save();

        if ($program !== null) {
            app(RolloutAuditLogger::class)->log('rollout.gate_approval_escalated', $program, [
                'request_id' => $request->id,
                'phase_key' => $request->phase_key,
                'role' => $role,
            ]);
        }
    }
}
