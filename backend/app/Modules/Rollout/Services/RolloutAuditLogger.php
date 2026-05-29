<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class RolloutAuditLogger
{
    public function __construct(
        private readonly RolloutBroadcaster $broadcaster,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, RolloutProgram $program, array $properties = [], ?Authenticatable $causer = null): void
    {
        if ($this->canPersist()) {
            $actor = $causer ?? Auth::user();

            activity('rollout')
                ->event($event)
                ->performedOn($program)
                ->causedBy($actor)
                ->withProperties(array_merge([
                    'rollout_id' => $program->id,
                    'rollout_ref' => $program->rollout_ref,
                ], $properties))
                ->log($this->description($event));
        }

        $this->broadcaster->fromAuditEvent($program, $event, $properties);
    }

    /**
     * @param  list<string>  $rolloutIds
     * @param  array<string, mixed>  $changes
     */
    public function logBulkMetadataUpdated(array $rolloutIds, array $changes, ?Authenticatable $causer = null): void
    {
        if ($rolloutIds === [] || ! $this->canPersist()) {
            return;
        }

        /** @var RolloutProgram|null $anchor */
        $anchor = RolloutProgram::query()->find($rolloutIds[0]);
        if ($anchor === null) {
            return;
        }

        $actor = $causer ?? Auth::user();

        activity('rollout')
            ->event('rollout.bulk_metadata_updated')
            ->performedOn($anchor)
            ->causedBy($actor)
            ->withProperties([
                'rollout_ids' => $rolloutIds,
                'rollout_count' => count($rolloutIds),
                'changes' => $changes,
            ])
            ->log('Bulk rollout metadata updated');

        foreach ($rolloutIds as $rolloutId) {
            /** @var RolloutProgram|null $program */
            $program = RolloutProgram::query()->find($rolloutId);
            if ($program === null) {
                continue;
            }

            $this->broadcaster->rolloutUpdated($program, 'rollout.metadata_updated');
        }
    }

    /**
     * @param  list<string>  $rolloutIds
     * @param  list<array{phase_key: string, actual_date: string}>  $phases
     */
    public function logBulkPhaseDatesBackfilled(
        array $rolloutIds,
        array $phases,
        bool $markGatePassed,
        ?Authenticatable $causer = null,
    ): void {
        if ($rolloutIds === [] || ! $this->canPersist()) {
            return;
        }

        /** @var RolloutProgram|null $anchor */
        $anchor = RolloutProgram::query()->find($rolloutIds[0]);
        if ($anchor === null) {
            return;
        }

        $actor = $causer ?? Auth::user();

        activity('rollout')
            ->event('rollout.bulk_phase_dates_backfilled')
            ->performedOn($anchor)
            ->causedBy($actor)
            ->withProperties([
                'rollout_ids' => $rolloutIds,
                'rollout_count' => count($rolloutIds),
                'phases' => $phases,
                'mark_gate_passed' => $markGatePassed,
            ])
            ->log('Bulk timeline phase dates backfilled');

        foreach ($rolloutIds as $rolloutId) {
            /** @var RolloutProgram|null $program */
            $program = RolloutProgram::query()->find($rolloutId);
            if ($program === null) {
                continue;
            }

            $this->broadcaster->rolloutUpdated($program, 'rollout.timeline_updated');
        }
    }

    private function description(string $event): string
    {
        return match ($event) {
            'rollout.created' => 'Rollout program created',
            'rollout.day_one_set' => 'Delivery period Day-1 recorded',
            'rollout.gate_updated' => 'Timeline gate status updated',
            'rollout.gate_approval_submitted' => 'Gate approval submitted',
            'rollout.gate_approval_step_approved' => 'Gate approval step completed',
            'rollout.gate_approval_completed' => 'Gate approval completed',
            'rollout.gate_approval_rejected' => 'Gate approval rejected',
            'rollout.gate_approval_escalated' => 'Gate approval escalated',
            'rollout.rfi_recorded' => 'RFI certificate recorded',
            'rollout.candidate_selected' => 'Site candidate selected',
            'rollout.metadata_updated' => 'Rollout metadata updated',
            'rollout.bulk_metadata_updated' => 'Bulk rollout metadata updated',
            'rollout.phase_actual_backfilled' => 'Timeline phase actual date backfilled',
            'rollout.bulk_phase_dates_backfilled' => 'Bulk timeline phase dates backfilled',
            'rollout.cancelled' => 'Rollout cancelled',
            default => 'Rollout event recorded',
        };
    }

    private function canPersist(): bool
    {
        $connection = (new RolloutProgram())->getConnectionName();

        return Schema::connection($connection)->hasTable('activity_log');
    }
}
