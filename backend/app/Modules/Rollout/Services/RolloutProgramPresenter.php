<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Documents\Services\DocumentRolloutGateEnforcementService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;

final class RolloutProgramPresenter
{
    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
        private readonly RolloutMediaAttachmentService $media,
        private readonly RolloutMilestoneCyclePresenter $milestoneCycles,
        private readonly RolloutGateApprovalService $gateApprovals,
        private readonly RolloutGateApprovalPolicyService $gatePolicies,
        private readonly DocumentRolloutGateEnforcementService $documentGateEnforcement,
        private readonly RolloutPermitService $permits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(RolloutProgram $program, ?TenantUser $viewer = null): array
    {
        $program->load([
            'timelinePhases',
            'candidates',
            'huntingLogs',
            'cmeReports',
            'profitability',
            'site',
            'project',
            'children' => static fn ($q) => $q->with('site')->orderBy('rollout_ref'),
        ]);

        $slaRemaining = null;
        if ($program->tssr_approved_date !== null && $program->target_rfi_working_date !== null) {
            $slaRemaining = $this->calendarFactory
                ->make($program->region)
                ->workingDaysBetween(
                    Carbon::today(),
                    Carbon::parse($program->target_rfi_working_date),
                );
        }

        $milestoneCycleRows = $program->status === 'batch'
            ? []
            : $this->milestoneCycles->forProgram($program, $program->timelinePhases);
        $config = TenantRolloutPlaybookConfig::query()->first();

        return [
            'id' => $program->id,
            'rollout_ref' => $program->rollout_ref,
            'tco_site_id' => $program->tco_site_id,
            'playbook_version' => $program->playbook_version,
            'mno' => $program->mno,
            'project_type' => $program->project_type,
            'status' => $program->status,
            'cancellation_reason' => $program->cancellation_reason,
            'cancelled_at' => $program->cancelled_at?->toIso8601String(),
            'endorsement_ref' => $program->endorsement_ref,
            'endorsement_date' => $program->endorsement_date?->toDateString(),
            'search_ring_name' => $program->search_ring_name,
            'region' => $program->region,
            'territory' => $program->territory,
            'area' => $program->area,
            'alliance_tag' => $program->alliance_tag,
            'mno_anchor_site_id' => $program->mno_anchor_site_id,
            'site_license_remarks' => $program->site_license_remarks,
            'energization_tempo_date' => $program->energization_tempo_date?->toDateString(),
            'rfti_signed_tempo_date' => $program->rfti_signed_tempo_date?->toDateString(),
            'saq_owner_id' => $program->saq_owner_id,
            'cme_pm_id' => $program->cme_pm_id,
            'pmo_owner_id' => $program->pmo_owner_id,
            'tssr_approved_date' => $program->tssr_approved_date?->toDateString(),
            'doa_execution_date' => $program->doa_execution_date?->toDateString(),
            'site_license_executed_date' => $program->site_license_executed_date?->toDateString(),
            'sla_working_days' => $program->sla_working_days,
            'target_rfi_working_date' => $program->target_rfi_working_date?->toDateString(),
            'actual_rfi_date' => $program->actual_rfi_date?->toDateString(),
            'sla_variance_working_days' => $program->sla_variance_working_days,
            'sla_working_days_remaining' => $slaRemaining,
            'sla_holiday_scope' => $this->calendarFactory->holidayScopeLabel($program->region),
            'site' => $program->site ? [
                'id' => $program->site->id,
                'site_code' => $program->site->site_code,
                'name' => $program->site->name,
                'full_address' => $program->site->full_address,
                'latitude' => $program->site->latitude !== null ? (float) $program->site->latitude : null,
                'longitude' => $program->site->longitude !== null ? (float) $program->site->longitude : null,
            ] : null,
            'project' => $program->project ? [
                'id' => $program->project->id,
                'name' => $program->project->name,
                'status' => $program->project->status,
            ] : null,
            'is_batch' => $program->status === 'batch',
            'parent_rollout_id' => $program->parent_rollout_id,
            'batch_children' => $program->status === 'batch'
                ? $program->children->map(static fn ($c) => [
                    'id' => $c->id,
                    'rollout_ref' => $c->rollout_ref,
                    'search_ring_name' => $c->search_ring_name,
                    'status' => $c->status,
                    'tco_site_id' => $c->tco_site_id,
                ])->values()->all()
                : [],
            'colocation_tenants' => $program->status !== 'batch'
                ? $program->children->map(static fn ($c) => [
                    'id' => $c->id,
                    'rollout_ref' => $c->rollout_ref,
                    'mno' => $c->mno,
                    'tco_site_id' => $c->tco_site_id,
                    'status' => $c->status,
                    'actual_rfi_date' => $c->actual_rfi_date?->toDateString(),
                    'site_license_remarks' => $c->site_license_remarks,
                    'site_name' => $c->site?->name,
                ])->values()->all()
                : [],
            'permits' => $program->status === 'batch' ? [] : $this->permits->listForProgram($program),
            'timeline_phases' => $program->status === 'batch' ? [] : $program->timelinePhases->map(function ($p) use ($program, $config, $viewer) {
                $active = $this->gateApprovals->activeRequestForPhase($p);
                $latest = $this->gateApprovals->latestRequestForPhase($p);
                $policy = $this->gatePolicies->policyForPhase((string) $program->project_type, (string) $p->phase_key, $config);

                return [
                    'id' => $p->id,
                    'phase_key' => $p->phase_key,
                    'label' => $p->label,
                    'owner_role' => $p->owner_role,
                    'anchor' => $p->anchor,
                    'working_day_start' => $p->working_day_start,
                    'working_day_end' => $p->working_day_end,
                    'target_start_date' => $p->target_start_date?->toDateString(),
                    'target_end_date' => $p->target_end_date?->toDateString(),
                    'actual_start_date' => $p->actual_start_date?->toDateString(),
                    'actual_end_date' => $p->actual_end_date?->toDateString(),
                    'gate_status' => $p->gate_status,
                    'gate_label' => $p->gate_label,
                    'counts_toward_sla' => (bool) ($p->counts_toward_sla ?? true),
                    'is_custom' => (bool) ($p->is_custom ?? false),
                    'phase_progress' => $this->phaseProgress($p),
                    'approval_required' => $policy !== null,
                    'approval_chain' => $policy['chain'] ?? [],
                    'active_gate_approval' => $active
                        ? $this->gateApprovals->presentRequest($active, $viewer)
                        : null,
                    'latest_gate_approval' => $latest && ($active === null || $latest->id !== $active->id)
                        ? $this->gateApprovals->presentRequest($latest, $viewer)
                        : ($active ? null : ($latest ? $this->gateApprovals->presentRequest($latest, $viewer) : null)),
                    'document_binder_gate' => $this->documentGateEnforcement->phaseSummary($program, $p),
                ];
            })->values()->all(),
            'candidates' => $program->status === 'batch' ? [] : $program->candidates->map(fn ($c) => [
                'id' => $c->id,
                'candidate_number' => $c->candidate_number,
                'status' => $c->status,
                'label' => $c->label,
                'latitude' => $c->latitude,
                'longitude' => $c->longitude,
                'coordinate_capture_method' => $c->coordinate_capture_method,
                'coordinate_accuracy_m' => $c->coordinate_accuracy_m,
                'coordinates_captured_at' => $c->coordinates_captured_at?->toIso8601String(),
                'lessor_name' => $c->lessor_name,
                'lessor_contact' => $c->lessor_contact,
                'proposed_lease_rate_php' => $c->proposed_lease_rate_php,
                'row_notes' => $c->row_notes,
                'power_notes' => $c->power_notes,
                'hazard_notes' => $c->hazard_notes,
                'photo_links' => $this->media->enrichPhotoLinks($c->photo_links),
                'lease_package' => $this->media->enrichLeasePackage($c->lease_package),
                'rejection_reason_code' => $c->rejection_reason_code,
                'selected_at' => $c->selected_at?->toIso8601String(),
            ])->values()->all(),
            'hunting_logs' => $program->huntingLogs->map(fn ($l) => [
                'id' => $l->id,
                'log_date' => $l->log_date?->toDateString(),
                'summary' => $l->summary,
                'candidates_identified_count' => $l->candidates_identified_count,
                'photo_links' => $this->media->enrichPhotoLinks($l->photo_links),
            ])->values()->all(),
            'cme_reports' => $program->cmeReports->take(14)->map(fn ($r) => [
                'id' => $r->id,
                'timeline_phase_id' => $r->timeline_phase_id,
                'report_date' => $r->report_date?->toDateString(),
                'day_number' => $r->day_number,
                'physical_progress_pct' => $r->physical_progress_pct,
                'physical_progress_plan_pct' => $r->physical_progress_plan_pct,
                'workforce_count' => $r->workforce_count,
                'weather_am' => $r->weather_am,
                'weather_pm' => $r->weather_pm,
                'manhours_today' => $r->manhours_today,
                'manhours_cumulative' => $r->manhours_cumulative,
                'quality_issues' => $r->quality_issues,
                'safety_incidents' => $r->safety_incidents,
                'activities_completed' => $r->activities_completed,
                'activities_planned_tomorrow' => $r->activities_planned_tomorrow,
                'toolbox_meeting_held' => $r->toolbox_meeting_held,
                'photo_links' => $this->media->enrichPhotoLinks($r->photo_links),
            ])->values()->all(),
            'milestone_cycles' => $milestoneCycleRows,
            'milestone_cycles_summary' => $this->milestoneCycles->summarize($milestoneCycleRows),
        ];
    }

    private function phaseProgress(RolloutTimelinePhase $phase): string
    {
        if ($phase->gate_status === 'passed' || $phase->actual_end_date !== null) {
            return 'completed';
        }

        if ($phase->gate_status === 'failed') {
            return 'overdue';
        }

        $today = Carbon::today();

        if ($phase->target_end_date !== null && $phase->target_end_date->lt($today)) {
            return 'overdue';
        }

        if ($phase->target_start_date !== null && $phase->target_start_date->lte($today)) {
            return 'active';
        }

        return 'pending';
    }
}
