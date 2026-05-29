<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;

final class RolloutProgramExportService
{
    public function __construct(
        private readonly RolloutProgramIndexService $index,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    public function headers(array $filters = []): array
    {
        return array_merge($this->baseHeaders(), $this->phaseHeaders($this->index->flattenedForExport($filters)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, list<string|null>>
     */
    public function rows(array $filters = []): \Generator
    {
        $programs = $this->index->flattenedForExport($filters);
        $phaseKeys = $this->collectPhaseKeys($programs);

        foreach ($programs as $program) {
            yield $this->buildRow($program, $phaseKeys);
        }
    }

    /**
     * @return list<string>
     */
    private function baseHeaders(): array
    {
        return [
            'id',
            'rollout_ref',
            'parent_rollout_ref',
            'mno',
            'project_type',
            'status',
            'region',
            'territory',
            'search_ring_name',
            'endorsement_ref',
            'tco_site_id',
            'site_code',
            'project_name',
            'saq_owner_name',
            'saq_owner_email',
            'pmo_owner_name',
            'pmo_owner_email',
            'cme_pm_name',
            'cme_pm_email',
            'endorsement_date',
            'tssr_approved_date',
            'doa_execution_date',
            'site_license_executed_date',
            'target_rfi_date',
            'actual_rfi_date',
            'sla_working_days',
            'sla_variance_working_days',
            'candidate_count',
            'playbook_version',
            'cancelled_at',
            'cancellation_reason',
        ];
    }

    /**
     * @param  list<RolloutProgram>  $programs
     * @return list<string>
     */
    private function phaseHeaders(array $programs): array
    {
        $headers = [];

        foreach ($this->collectPhaseKeys($programs) as $phaseKey) {
            $headers[] = "phase_{$phaseKey}_actual_start_date";
            $headers[] = "phase_{$phaseKey}_actual_end_date";
            $headers[] = "phase_{$phaseKey}_gate_status";
            $headers[] = "phase_{$phaseKey}_target_end_date";
        }

        return $headers;
    }

    /**
     * @param  list<RolloutProgram>  $programs
     * @return list<string>
     */
    private function collectPhaseKeys(array $programs): array
    {
        $keys = [];

        foreach ($programs as $program) {
            foreach ($program->timelinePhases as $phase) {
                $phaseKey = (string) $phase->phase_key;
                if ($phaseKey === '') {
                    continue;
                }

                $keys[$phaseKey] = min($keys[$phaseKey] ?? PHP_INT_MAX, (int) $phase->sort_order);
            }
        }

        asort($keys, SORT_NUMERIC);

        return array_keys($keys);
    }

    /**
     * @param  list<string>  $phaseKeys
     * @return list<string|null>
     */
    private function buildRow(RolloutProgram $program, array $phaseKeys): array
    {
        $phasesByKey = $program->timelinePhases->keyBy('phase_key');

        $base = [
            $program->id,
            $program->rollout_ref,
            $program->parent?->rollout_ref,
            $program->mno,
            $program->project_type,
            $program->status,
            $program->region,
            $program->territory,
            $program->search_ring_name,
            $program->endorsement_ref,
            $program->tco_site_id,
            $program->site?->site_code,
            $program->project?->name,
            $program->saqOwner?->name,
            $program->saqOwner?->email,
            $program->pmoOwner?->name,
            $program->pmoOwner?->email,
            $program->cmePm?->name,
            $program->cmePm?->email,
            $program->endorsement_date?->toDateString(),
            $program->tssr_approved_date?->toDateString(),
            $program->doa_execution_date?->toDateString(),
            $program->site_license_executed_date?->toDateString(),
            $program->target_rfi_working_date?->toDateString(),
            $program->actual_rfi_date?->toDateString(),
            $program->sla_working_days !== null ? (string) $program->sla_working_days : null,
            $program->sla_variance_working_days !== null ? (string) $program->sla_variance_working_days : null,
            $program->status === 'batch' ? '0' : (string) $program->candidates->count(),
            $program->playbook_version,
            $program->cancelled_at?->toDateString(),
            $program->cancellation_reason,
        ];

        foreach ($phaseKeys as $phaseKey) {
            /** @var RolloutTimelinePhase|null $phase */
            $phase = $phasesByKey->get($phaseKey);

            if ($phase === null) {
                $base[] = null;
                $base[] = null;
                $base[] = null;
                $base[] = null;

                continue;
            }

            $base[] = $phase->actual_start_date?->toDateString();
            $base[] = $phase->actual_end_date?->toDateString();
            $base[] = $phase->gate_status;
            $base[] = $phase->target_end_date?->toDateString();
        }

        return $base;
    }
}
