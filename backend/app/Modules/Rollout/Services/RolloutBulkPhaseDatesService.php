<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class RolloutBulkPhaseDatesService
{
    public function __construct(
        private readonly RolloutProgramService $programService,
        private readonly RolloutAuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $rolloutIds
     * @param  list<array{phase_key: string, actual_date: string}>  $phaseDates
     * @return array{
     *     updated: int,
     *     failed: int,
     *     phases_applied: int,
     *     results: list<array{
     *         id: string,
     *         status: string,
     *         phases_updated?: int,
     *         reason?: string
     *     }>
     * }
     */
    public function bulkApply(
        array $rolloutIds,
        array $phaseDates,
        bool $markGatePassed = true,
        ?Authenticatable $actor = null,
    ): array {
        if ($phaseDates === []) {
            throw ValidationException::withMessages([
                'phases' => [__('At least one phase with an actual date is required.')],
            ]);
        }

        $parsedPhases = $this->parsePhaseDateEntries($phaseDates, 'phases');

        return $this->runBulkApply(
            array_map(static fn (string $id): array => [
                'rollout_id' => $id,
                'phases' => $parsedPhases,
            ], $rolloutIds),
            $markGatePassed,
            $actor,
        );
    }

    /**
     * Per-rollout grid: each row may set different phases and dates.
     *
     * @param  list<array{rollout_id: string, phases: list<array{phase_key: string, actual_date: string}>}>  $rows
     * @return array{
     *     updated: int,
     *     failed: int,
     *     phases_applied: int,
     *     results: list<array{
     *         id: string,
     *         status: string,
     *         phases_updated?: int,
     *         reason?: string
     *     }>
     * }
     */
    public function bulkApplyGrid(
        array $rows,
        bool $markGatePassed = true,
        ?Authenticatable $actor = null,
    ): array {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'rows' => [__('At least one rollout row is required.')],
            ]);
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            $rolloutId = (string) ($row['rollout_id'] ?? '');
            if ($rolloutId === '') {
                throw ValidationException::withMessages([
                    "rows.{$index}.rollout_id" => [__('Rollout id is required.')],
                ]);
            }

            $phases = $row['phases'] ?? [];
            if (! is_array($phases) || $phases === []) {
                continue;
            }

            $normalized[] = [
                'rollout_id' => $rolloutId,
                'phases' => $this->parsePhaseDateEntries($phases, "rows.{$index}.phases"),
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'rows' => [__('Enter at least one date in the grid.')],
            ]);
        }

        return $this->runBulkApply($normalized, $markGatePassed, $actor);
    }

    /**
     * @param  list<array{rollout_id: string, phases: list<array{phase_key: string, actual_date: Carbon}>}>  $rows
     * @return array{
     *     updated: int,
     *     failed: int,
     *     phases_applied: int,
     *     results: list<array{
     *         id: string,
     *         status: string,
     *         phases_updated?: int,
     *         reason?: string
     *     }>
     * }
     */
    private function runBulkApply(array $rows, bool $markGatePassed, ?Authenticatable $actor): array
    {
        $results = [];
        $updated = 0;
        $failed = 0;
        $phasesApplied = 0;
        $updatedIds = [];
        $auditPhaseSummary = [];

        foreach ($rows as $row) {
            $rolloutId = $row['rollout_id'];
            /** @var RolloutProgram|null $program */
            $program = RolloutProgram::query()->with('timelinePhases')->find($rolloutId);

            if ($program === null) {
                $results[] = [
                    'id' => $rolloutId,
                    'status' => 'failed',
                    'reason' => __('Rollout not found.'),
                ];
                $failed++;

                continue;
            }

            try {
                $this->assertRolloutEligible($program);
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
                $results[] = [
                    'id' => (string) $program->id,
                    'status' => 'skipped',
                    'reason' => is_string($message) ? $message : __('Rollout cannot be updated.'),
                ];
                $failed++;

                continue;
            }

            $phasesByKey = $program->timelinePhases->keyBy('phase_key');
            $rolloutPhasesUpdated = 0;
            $phaseErrors = [];

            foreach ($row['phases'] as $entry) {
                /** @var RolloutTimelinePhase|null $phase */
                $phase = $phasesByKey->get($entry['phase_key']);

                if ($phase === null) {
                    $phaseErrors[] = __('Phase :key does not exist on this rollout.', ['key' => $entry['phase_key']]);

                    continue;
                }

                try {
                    $this->programService->backfillPhaseActualDate(
                        $phase,
                        $entry['actual_date'],
                        $markGatePassed,
                    );
                    $rolloutPhasesUpdated++;
                    $phasesApplied++;
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->first();
                    $phaseErrors[] = is_string($message)
                        ? $message
                        : __('Could not update phase :key.', ['key' => $entry['phase_key']]);
                }
            }

            if ($rolloutPhasesUpdated > 0) {
                $results[] = [
                    'id' => (string) $program->id,
                    'status' => 'updated',
                    'phases_updated' => $rolloutPhasesUpdated,
                ];
                $updated++;
                $updatedIds[] = (string) $program->id;
                $auditPhaseSummary[] = [
                    'rollout_id' => (string) $program->id,
                    'rollout_ref' => $program->rollout_ref,
                    'phases' => array_map(static fn (array $phase): array => [
                        'phase_key' => $phase['phase_key'],
                        'actual_date' => $phase['actual_date']->toDateString(),
                    ], $row['phases']),
                ];
            } else {
                $results[] = [
                    'id' => (string) $program->id,
                    'status' => 'skipped',
                    'reason' => $phaseErrors !== []
                        ? implode(' ', array_unique($phaseErrors))
                        : __('No phases were updated.'),
                ];
                $failed++;
            }
        }

        if ($updatedIds !== []) {
            $this->audit->logBulkPhaseDatesBackfilled(
                $updatedIds,
                $auditPhaseSummary,
                $markGatePassed,
                $actor ?? Auth::user(),
            );
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'phases_applied' => $phasesApplied,
            'results' => $results,
        ];
    }

    /**
     * @param  list<array{phase_key?: string, actual_date?: string}>  $phaseDates
     * @return list<array{phase_key: string, actual_date: Carbon}>
     */
    private function parsePhaseDateEntries(array $phaseDates, string $errorPrefix): array
    {
        $parsedPhases = [];

        foreach ($phaseDates as $index => $entry) {
            $phaseKey = (string) ($entry['phase_key'] ?? '');
            $dateRaw = (string) ($entry['actual_date'] ?? '');

            if ($phaseKey === '' || $dateRaw === '') {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}" => [__('Each phase must include phase_key and actual_date.')],
                ]);
            }

            $parsedPhases[] = [
                'phase_key' => $phaseKey,
                'actual_date' => Carbon::parse($dateRaw)->startOfDay(),
            ];
        }

        return $parsedPhases;
    }

    private function assertRolloutEligible(RolloutProgram $program): void
    {
        if (in_array($program->status, ['completed', 'cancelled', 'batch'], true)) {
            throw ValidationException::withMessages([
                'rollout' => [__('Completed, cancelled, and batch rollouts cannot be backfilled.')],
            ]);
        }
    }
}
