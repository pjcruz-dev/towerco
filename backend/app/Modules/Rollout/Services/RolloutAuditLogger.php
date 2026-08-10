<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class RolloutAuditLogger
{
    public function __construct(
        private readonly RolloutBroadcaster $broadcaster,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, RolloutProgram $program, array $properties = [], ?Authenticatable $causer = null): void
    {
        $actor = $causer ?? Auth::user();

        if ($this->canPersist()) {
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

        $this->writeWorkspace($event, $program, $properties, $actor);
        $this->broadcaster->fromAuditEvent($program, $event, $properties);
    }

    /**
     * @param  list<string>  $rolloutIds
     * @param  array<string, mixed>  $changes
     */
    public function logBulkMetadataUpdated(array $rolloutIds, array $changes, ?Authenticatable $causer = null): void
    {
        if ($rolloutIds === []) {
            return;
        }

        /** @var RolloutProgram|null $anchor */
        $anchor = RolloutProgram::query()->find($rolloutIds[0]);
        if ($anchor === null) {
            return;
        }

        $actor = $causer ?? Auth::user();

        if ($this->canPersist()) {
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
        }

        $this->writeWorkspace(
            'rollout.bulk_metadata_updated',
            $anchor,
            [
                'rollout_ids' => $rolloutIds,
                'rollout_count' => count($rolloutIds),
                'changes' => $changes,
            ],
            $actor,
        );

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
        if ($rolloutIds === []) {
            return;
        }

        /** @var RolloutProgram|null $anchor */
        $anchor = RolloutProgram::query()->find($rolloutIds[0]);
        if ($anchor === null) {
            return;
        }

        $actor = $causer ?? Auth::user();

        if ($this->canPersist()) {
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
        }

        $this->writeWorkspace(
            'rollout.bulk_phase_dates_backfilled',
            $anchor,
            [
                'rollout_ids' => $rolloutIds,
                'rollout_count' => count($rolloutIds),
                'phases' => $phases,
                'mark_gate_passed' => $markGatePassed,
            ],
            $actor,
        );

        foreach ($rolloutIds as $rolloutId) {
            /** @var RolloutProgram|null $program */
            $program = RolloutProgram::query()->find($rolloutId);
            if ($program === null) {
                continue;
            }

            $this->broadcaster->rolloutUpdated($program, 'rollout.timeline_updated');
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function writeWorkspace(
        string $event,
        RolloutProgram $program,
        array $properties,
        Authenticatable|TenantUser|null $actor,
    ): void {
        $changes = [];
        if (isset($properties['changes']) && is_array($properties['changes'])) {
            $raw = $properties['changes'];
            // Bulk metadata may already be field => {from,to} or field => value.
            $looksLikeDiff = false;
            foreach ($raw as $value) {
                if (is_array($value) && array_key_exists('from', $value) && array_key_exists('to', $value)) {
                    $looksLikeDiff = true;
                    break;
                }
            }
            if ($looksLikeDiff) {
                $changes = $raw;
                unset($properties['changes']);
            }
        }

        $this->activity->record(
            module: 'project_one',
            action: $event,
            summary: $this->description($event).($program->rollout_ref ? ' · '.$program->rollout_ref : ''),
            entityType: 'rollout',
            entityId: (string) $program->id,
            entityLabel: $program->rollout_ref,
            actor: $actor instanceof Authenticatable || $actor instanceof TenantUser ? $actor : null,
            metadata: $properties,
            changes: WorkspaceAuditChanges::of($changes),
        );
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
            'rollout.site_license_recorded' => 'Site license executed recorded',
            'rollout.candidate_selected' => 'Site candidate selected',
            'rollout.metadata_updated' => 'Rollout metadata updated',
            'rollout.bulk_metadata_updated' => 'Bulk rollout metadata updated',
            'rollout.phase_actual_backfilled' => 'Timeline phase actual date backfilled',
            'rollout.bulk_phase_dates_backfilled' => 'Bulk timeline phase dates backfilled',
            'rollout.cancelled' => 'Rollout cancelled',
            'rollout.import_backfilled' => 'Rollout import backfilled',
            'rollout.site_profile_updated' => 'Site profile updated',
            'permits_updated' => 'Permits updated',
            default => 'Rollout event recorded',
        };
    }

    private function canPersist(): bool
    {
        $connection = (new RolloutProgram())->getConnectionName();

        return Schema::connection($connection)->hasTable('activity_log');
    }
}
