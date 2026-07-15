<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Support\RolloutPermitCatalog;

final class RolloutProgramExportService
{
    public function __construct(
        private readonly RolloutProgramIndexService $index,
        private readonly RolloutPermitService $permits,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    public function headers(array $filters = []): array
    {
        return array_merge(
            $this->baseHeaders(),
            $this->permitHeaders(),
            $this->legacyManualHeaders(),
            $this->phaseHeaders($this->index->flattenedForExport($filters)),
        );
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
            'area',
            'alliance_tag',
            'mno_anchor_site_id',
            'search_ring_name',
            'endorsement_ref',
            'tco_site_id',
            'site_code',
            'site_name',
            'site_full_address',
            'site_latitude',
            'site_longitude',
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
            'site_license_remarks',
            'energization_tempo_date',
            'rfti_signed_tempo_date',
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
     * @return list<string>
     */
    private function permitHeaders(): array
    {
        $headers = [];
        foreach (RolloutPermitCatalog::typeKeys() as $type) {
            $headers[] = "permit_{$type}_applied_date";
            $headers[] = "permit_{$type}_secured_date";
        }

        return $headers;
    }

    /**
     * Legacy manual tracker column names for one-time migration parity reports.
     *
     * @return list<string>
     */
    private function legacyManualHeaders(): array
    {
        return [
            'manual_moc_secured',
            'manual_locational_clearance_applied',
            'manual_locational_clearance_secured',
            'manual_building_permit_applied',
            'manual_building_permit_secured',
            'manual_risk_build_declared_date',
            'manual_cw_start_date',
            'manual_cw_completed_date',
            'manual_energization_permanent',
            'manual_rfti_docs_submitted',
            'manual_rft_docs_signed_permanent',
            'manual_sl_submitted',
            'manual_sl_signed',
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
        $permitFlat = $this->permits->flattenForExport($program);

        $base = [
            $program->id,
            $program->rollout_ref,
            $program->parent?->rollout_ref,
            $program->mno,
            $program->project_type,
            $program->status,
            $program->region,
            $program->territory,
            $program->area,
            $program->alliance_tag,
            $program->mno_anchor_site_id,
            $program->search_ring_name,
            $program->endorsement_ref,
            $program->tco_site_id,
            $program->site?->site_code,
            $program->site?->name,
            $program->site?->full_address,
            $program->site?->latitude !== null ? (string) $program->site->latitude : null,
            $program->site?->longitude !== null ? (string) $program->site->longitude : null,
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
            $program->site_license_remarks,
            $program->energization_tempo_date?->toDateString(),
            $program->rfti_signed_tempo_date?->toDateString(),
            $program->target_rfi_working_date?->toDateString(),
            $program->actual_rfi_date?->toDateString(),
            $program->sla_working_days !== null ? (string) $program->sla_working_days : null,
            $program->sla_variance_working_days !== null ? (string) $program->sla_variance_working_days : null,
            $program->status === 'batch' ? '0' : (string) $program->candidates->count(),
            $program->playbook_version,
            $program->cancelled_at?->toDateString(),
            $program->cancellation_reason,
        ];

        foreach (RolloutPermitCatalog::typeKeys() as $type) {
            $base[] = $permitFlat["permit_{$type}_applied_date"] ?? null;
            $base[] = $permitFlat["permit_{$type}_secured_date"] ?? null;
        }

        $base = array_merge($base, $this->legacyManualValues($program, $phasesByKey, $permitFlat));

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

    /**
     * @param  \Illuminate\Support\Collection<string, RolloutTimelinePhase>  $phasesByKey
     * @param  array<string, string|null>  $permitFlat
     * @return list<string|null>
     */
    private function legacyManualValues(RolloutProgram $program, $phasesByKey, array $permitFlat): array
    {
        return [
            $permitFlat['permit_moc_secured_date'] ?? $phasesByKey->get('moc_col')?->actual_end_date?->toDateString(),
            $permitFlat['permit_locational_clearance_applied_date'] ?? $phasesByKey->get('permitting')?->actual_start_date?->toDateString(),
            $permitFlat['permit_locational_clearance_secured_date'] ?? $phasesByKey->get('permitting')?->actual_end_date?->toDateString(),
            $permitFlat['permit_building_permit_applied_date'] ?? null,
            $permitFlat['permit_building_permit_secured_date'] ?? null,
            $phasesByKey->get('permitting')?->actual_end_date?->toDateString(),
            $phasesByKey->get('construction')?->actual_start_date?->toDateString(),
            $phasesByKey->get('construction')?->actual_end_date?->toDateString(),
            $phasesByKey->get('construction')?->actual_end_date?->toDateString(),
            null,
            $program->actual_rfi_date?->toDateString(),
            null,
            $program->site_license_executed_date?->toDateString(),
        ];
    }
}
