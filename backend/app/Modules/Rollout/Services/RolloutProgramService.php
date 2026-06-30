<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantRfiMeterService;
use App\Modules\Documents\Services\DocumentRolloutGateEnforcementService;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\SiteProfitabilityRecord;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RolloutProgramService
{
    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
        private readonly TcoSiteIdGenerator $tcoSiteIdGenerator,
        private readonly RolloutSlaRecalculationService $slaRecalculation,
        private readonly RolloutAuditLogger $audit,
        private readonly TenantRfiMeterService $rfiMeter,
        private readonly DocumentRolloutGateEnforcementService $documentGateEnforcement,
        private readonly RolloutCanonicalSiteService $canonicalSites,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): RolloutProgram
    {
        $config = TenantRolloutPlaybookConfig::query()->first();
        if ($config === null) {
            throw ValidationException::withMessages([
                'playbook' => [__('No rollout playbook assigned to this tenant.')],
            ]);
        }

        $projectType = strtolower((string) ($input['project_type'] ?? 'bts'));
        $slaDays = $this->slaWorkingDays($config, $projectType);
        $endorsementDate = isset($input['endorsement_date']) && $input['endorsement_date'] !== ''
            ? Carbon::parse((string) $input['endorsement_date'])
            : null;

        $rolloutRef = (string) ($input['rollout_ref'] ?? $this->generateRolloutRef($input));

        /** @var RolloutProgram $program */
        $program = RolloutProgram::query()->create([
            'parent_rollout_id' => $input['parent_rollout_id'] ?? null,
            'playbook_version' => $config->assigned_version,
            'rollout_ref' => $rolloutRef,
            'mno' => strtolower((string) $input['mno']),
            'project_type' => $projectType,
            'endorsement_ref' => $input['endorsement_ref'] ?? null,
            'endorsement_date' => $endorsementDate?->toDateString(),
            'search_ring_name' => $input['search_ring_name'] ?? null,
            'region' => $input['region'] ?? null,
            'territory' => $input['territory'] ?? null,
            'status' => 'saq',
            'sla_working_days' => $slaDays,
            'saq_owner_id' => $input['saq_owner_id'] ?? null,
            'cme_pm_id' => $input['cme_pm_id'] ?? null,
            'pmo_owner_id' => $input['pmo_owner_id'] ?? null,
            'project_id' => $input['project_id'] ?? null,
        ]);

        $this->assertProjectSiteMatch($program, $program->project_id);

        $this->instantiateTimeline($program, $config);
        $this->ensureProfitabilityShell($program);

        $this->canonicalSites->ensureForProgram($program);

        $this->audit->log('rollout.created', $program, [
            'mno' => $program->mno,
            'project_type' => $program->project_type,
        ]);

        return $program->fresh(['timelinePhases', 'profitability', 'site']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<array<string, mixed>>  $sites
     * @return array{parent: RolloutProgram, children: list<RolloutProgram>}
     */
    public function createBatch(array $input, array $sites): array
    {
        if ($sites === []) {
            throw ValidationException::withMessages([
                'sites' => [__('At least one site entry is required for a batch rollout.')],
            ]);
        }

        $batchLabel = (string) ($input['batch_label'] ?? $input['search_ring_name'] ?? 'Batch rollout');
        $parentRef = (string) ($input['rollout_ref'] ?? $this->generateBatchRef($input));
        $config = TenantRolloutPlaybookConfig::query()->firstOrFail();

        /** @var RolloutProgram $parent */
        $parent = RolloutProgram::query()->create([
            'playbook_version' => $config->assigned_version,
            'rollout_ref' => $parentRef,
            'mno' => strtolower((string) $input['mno']),
            'project_type' => strtolower((string) ($input['project_type'] ?? 'bts')),
            'endorsement_ref' => $input['endorsement_ref'] ?? null,
            'endorsement_date' => isset($input['endorsement_date']) && $input['endorsement_date'] !== ''
                ? Carbon::parse((string) $input['endorsement_date'])->toDateString()
                : null,
            'search_ring_name' => $batchLabel,
            'region' => $input['region'] ?? null,
            'territory' => $input['territory'] ?? null,
            'status' => 'batch',
            'sla_working_days' => 0,
        ]);

        $children = [];
        foreach ($sites as $index => $siteInput) {
            $childRef = sprintf('%s-S%02d', $parentRef, $index + 1);
            $children[] = $this->create(array_merge($siteInput, [
                'mno' => $input['mno'],
                'project_type' => $input['project_type'] ?? 'bts',
                'endorsement_ref' => $input['endorsement_ref'] ?? null,
                'endorsement_date' => $input['endorsement_date'] ?? null,
                'parent_rollout_id' => $parent->id,
                'rollout_ref' => $siteInput['rollout_ref'] ?? $childRef,
            ]));
        }

        return [
            'parent' => $parent->fresh(['children']),
            'children' => $children,
        ];
    }

    public function setTssrApproved(RolloutProgram $program, Carbon $tssrApprovedDate): RolloutProgram
    {
        return $this->applyDeliveryPeriodStart($program, $tssrApprovedDate, [
            'tssr_approved_date' => $tssrApprovedDate,
        ], 'tssr_approved');
    }

    public function setDoaExecution(RolloutProgram $program, Carbon $doaExecutionDate): RolloutProgram
    {
        $deliveryStart = $this->calendarFactory->make($program->region)->addWorkingDays($doaExecutionDate, 15);

        return $this->applyDeliveryPeriodStart($program, $deliveryStart, [
            'doa_execution_date' => $doaExecutionDate,
            'tssr_approved_date' => $deliveryStart,
        ], 'doa_execution_plus_15wd');
    }

    public function setSiteLicenseExecuted(RolloutProgram $program, Carbon $siteLicenseDate): RolloutProgram
    {
        return $this->applyDeliveryPeriodStart($program, $siteLicenseDate, [
            'site_license_executed_date' => $siteLicenseDate,
            'tssr_approved_date' => $siteLicenseDate,
        ], 'site_license_executed');
    }

    /**
     * @param  array<string, Carbon>  $dates
     */
    private function applyDeliveryPeriodStart(
        RolloutProgram $program,
        Carbon $deliveryStart,
        array $dates,
        string $triggerType,
    ): RolloutProgram {
        foreach ($dates as $field => $value) {
            $program->{$field} = $value;
        }

        if ($program->status === 'saq') {
            $program->status = 'permitting';
        }

        $program->save();

        $updated = $this->slaRecalculation->recalculateProgram($program->fresh(['timelinePhases']));

        $this->audit->log('rollout.day_one_set', $updated, [
            'date' => $deliveryStart->toDateString(),
            'trigger_type' => $triggerType,
        ]);

        return $updated;
    }

    public function updatePhaseGateStatus(
        RolloutTimelinePhase $phase,
        string $gateStatus,
    ): RolloutTimelinePhase {
        $allowed = ['pending', 'passed', 'failed', 'waived'];
        if (! in_array($gateStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'gate_status' => [__('Invalid gate status.')],
            ]);
        }

        $phase->load('rolloutProgram');
        $program = $phase->rolloutProgram;

        if ($gateStatus === 'passed' && $program !== null && $phase->gate_label !== null) {
            $policy = app(RolloutGateApprovalPolicyService::class)->policyForPhase(
                (string) $program->project_type,
                (string) $phase->phase_key,
                TenantRolloutPlaybookConfig::query()->first(),
            );

            if ($policy !== null) {
                $approved = RolloutGateApprovalRequest::query()
                    ->where('rollout_timeline_phase_id', $phase->id)
                    ->where('status', RolloutGateApprovalRequest::STATUS_APPROVED)
                    ->exists();

                if (! $approved) {
                    throw ValidationException::withMessages([
                        'gate_status' => [__('This gate requires formal approval before it can be marked passed.')],
                    ]);
                }
            }

            $this->documentGateEnforcement->assertCanPassGate($program, $phase);
        }

        $phase->gate_status = $gateStatus;

        if ($gateStatus === 'passed' && $phase->actual_end_date === null) {
            $phase->actual_end_date = Carbon::today();
        }

        if ($gateStatus === 'pending') {
            $phase->actual_end_date = null;
        }

        $phase->save();

        $phase->load('rolloutProgram');
        if ($phase->rolloutProgram !== null) {
            $this->audit->log('rollout.gate_updated', $phase->rolloutProgram, [
                'phase_key' => $phase->phase_key,
                'gate_status' => $gateStatus,
            ]);
        }

        return $phase->fresh();
    }

    /**
     * Administrative backfill of a phase actual end date (does not require gate approval workflow).
     */
    public function backfillPhaseActualDate(
        RolloutTimelinePhase $phase,
        Carbon $actualEndDate,
        bool $markGatePassed = true,
    ): RolloutTimelinePhase {
        $phase->load('rolloutProgram');
        $program = $phase->rolloutProgram;

        if ($program === null) {
            throw ValidationException::withMessages([
                'phase' => [__('Rollout program not found for this phase.')],
            ]);
        }

        $this->assertProgramEditable($program);

        if ($markGatePassed) {
            $this->documentGateEnforcement->assertCanPassGate($program, $phase);
            $phase->gate_status = 'passed';
        }

        $phase->actual_end_date = $actualEndDate->copy()->startOfDay();
        $phase->save();

        $this->audit->log('rollout.phase_actual_backfilled', $program, [
            'phase_key' => $phase->phase_key,
            'actual_end_date' => $phase->actual_end_date?->toDateString(),
            'gate_status' => $phase->gate_status,
            'mark_gate_passed' => $markGatePassed,
        ]);

        return $phase->fresh();
    }

    public function recordRfi(RolloutProgram $program, Carbon $actualRfiDate): RolloutProgram
    {
        if ($program->status === 'batch') {
            throw ValidationException::withMessages([
                'actual_rfi_date' => [__('RFI cannot be recorded on a batch container rollout.')],
            ]);
        }

        if ($program->tssr_approved_date === null) {
            throw ValidationException::withMessages([
                'actual_rfi_date' => [__('Set delivery period start (Day-1) before recording RFI.')],
            ]);
        }

        $deliveryStart = Carbon::parse($program->tssr_approved_date);
        $elapsedWorkingDays = $this->calendarFactory
            ->make($program->region)
            ->workingDaysBetween($deliveryStart, $actualRfiDate);

        $central = $this->resolveCentralTenant();
        if ($central instanceof Tenant) {
            $this->rfiMeter->assertCanRecordRfi($central, $program, $actualRfiDate);
        }

        $program->actual_rfi_date = $actualRfiDate->toDateString();
        $program->sla_variance_working_days = $elapsedWorkingDays - $program->sla_working_days;
        $program->status = 'completed';
        $program->save();

        if ($central instanceof Tenant) {
            $this->rfiMeter->recordCompletion($central, $program->fresh(), $actualRfiDate);
        }

        $updated = $program->fresh(['timelinePhases']);

        $this->audit->log('rollout.rfi_recorded', $updated, [
            'actual_rfi_date' => $actualRfiDate->toDateString(),
            'variance' => $updated->sla_variance_working_days,
        ]);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateMetadata(RolloutProgram $program, array $input): RolloutProgram
    {
        if (in_array($program->status, ['completed', 'cancelled', 'batch'], true)) {
            throw ValidationException::withMessages([
                'rollout' => [__('This rollout cannot be edited.')],
            ]);
        }

        $allowed = [
            'search_ring_name',
            'region',
            'territory',
            'endorsement_ref',
            'endorsement_date',
            'saq_owner_id',
            'cme_pm_id',
            'pmo_owner_id',
            'project_id',
        ];

        $changes = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $input)) {
                if ($field === 'endorsement_date') {
                    if ($input[$field] === null || $input[$field] === '') {
                        $program->endorsement_date = null;
                    } else {
                        $program->endorsement_date = Carbon::parse((string) $input[$field])->toDateString();
                    }
                    $changes[$field] = $program->endorsement_date;
                } else {
                    $program->{$field} = $input[$field];
                    $changes[$field] = $input[$field];
                }
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'rollout' => [__('No editable fields were provided.')],
            ]);
        }

        $program->save();

        $this->assertProjectSiteMatch($program, $program->project_id);

        $updated = $program->fresh();

        if (array_key_exists('endorsement_date', $changes)) {
            $updated = $this->slaRecalculation->recalculateProgram($updated->load('timelinePhases'));
        }

        $this->audit->log('rollout.metadata_updated', $updated, [
            'changes' => $changes,
        ]);

        return $updated;
    }

    public function cancel(RolloutProgram $program, string $reason): RolloutProgram
    {
        if ($program->status === 'completed') {
            throw ValidationException::withMessages([
                'rollout' => [__('Completed rollouts cannot be cancelled.')],
            ]);
        }

        if ($program->status === 'batch') {
            throw ValidationException::withMessages([
                'rollout' => [__('Cancel each child rollout individually. Batch containers cannot be cancelled directly.')],
            ]);
        }

        if ($program->status === 'cancelled') {
            throw ValidationException::withMessages([
                'rollout' => [__('Rollout is already cancelled.')],
            ]);
        }

        $program->status = 'cancelled';
        $program->cancellation_reason = trim($reason);
        $program->cancelled_at = now();
        $program->save();

        $updated = $program->fresh(['timelinePhases']);

        $this->audit->log('rollout.cancelled', $updated, [
            'reason' => $program->cancellation_reason,
        ]);

        return $updated;
    }

    public function applyDayOverrides(array $overrides): void
    {
        $config = TenantRolloutPlaybookConfig::query()->firstOrFail();
        $config->day_overrides = $overrides;
        $config->save();
    }

    /**
     * @param  array<string, array<string, mixed>>  $policies
     */
    public function applyGateApprovalPolicies(array $policies): void
    {
        app(RolloutGateApprovalPolicyService::class)->saveTenantPolicies($policies);
    }

    /**
     * @param  array<string, mixed>  $policies
     */
    public function applyEmailNotificationPolicies(array $policies): void
    {
        app(RolloutEmailNotificationPolicyService::class)->saveTenantPolicies($policies);
    }

    public function applyGateApprovalEscalationWorkingDays(int $days): void
    {
        $config = TenantRolloutPlaybookConfig::query()->firstOrFail();
        $config->gate_approval_escalation_working_days = max(1, min(30, $days));
        $config->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function playbookStatus(): array
    {
        $config = TenantRolloutPlaybookConfig::query()->first();
        if ($config === null) {
            return [
                'assigned_version' => null,
                'latest_platform_version' => null,
                'upgrade_available' => false,
                'sla_working_days_only' => true,
            ];
        }

        return [
            'assigned_version' => $config->assigned_version,
            'latest_platform_version' => $config->latest_platform_version,
            'upgrade_available' => $config->latest_platform_version !== null
                && version_compare((string) $config->latest_platform_version, (string) $config->assigned_version, '>'),
            'sla_working_days_only' => (bool) ($config->playbook_snapshot['sla_working_days_only'] ?? true),
            'day_overrides' => $config->day_overrides,
            'gate_approval_policies' => app(RolloutGateApprovalPolicyService::class)->mergedPolicies($config),
            'email_notification_policies' => app(RolloutEmailNotificationPolicyService::class)->mergedPolicies($config),
            'gate_approval_escalation_working_days' => (int) ($config->gate_approval_escalation_working_days ?? 3),
            'timeline_templates' => $config->playbook_snapshot['timeline_templates'] ?? [],
            'delivery_periods' => $config->playbook_snapshot['delivery_periods'] ?? [],
            'public_holidays_count' => Schema::connection('tenant')->hasTable('tenant_public_holidays')
                ? TenantPublicHoliday::query()->where('calendar_year', (int) now()->format('Y'))->count()
                : 0,
            'national_holidays_count' => Schema::connection('tenant')->hasTable('tenant_public_holidays')
                ? TenantPublicHoliday::query()->where('calendar_year', (int) now()->format('Y'))->whereNull('region')->count()
                : 0,
            'regional_holidays_count' => Schema::connection('tenant')->hasTable('tenant_public_holidays')
                ? TenantPublicHoliday::query()->where('calendar_year', (int) now()->format('Y'))->whereNotNull('region')->count()
                : 0,
            'sla_holiday_policy' => 'National holidays apply to all rollouts. Regional holidays apply only when the rollout region matches.',
        ];
    }

    private function slaWorkingDays(TenantRolloutPlaybookConfig $config, string $projectType): int
    {
        $periods = $config->playbook_snapshot['delivery_periods'] ?? [];
        $days = (int) ($periods[$projectType]['working_days'] ?? 120);

        return match ($projectType) {
            'rtb' => (int) ($periods['rtb']['working_days'] ?? 85),
            'colocation', 'colo' => (int) ($periods['colocation']['working_days'] ?? 30),
            default => $days,
        };
    }

    private function instantiateTimeline(RolloutProgram $program, TenantRolloutPlaybookConfig $config): void
    {
        $templateKey = match ($program->project_type) {
            'rtb' => 'rtb',
            'colocation', 'colo' => 'colocation',
            default => 'bts',
        };

        $templates = $config->playbook_snapshot['timeline_templates'][$templateKey] ?? [];
        $overrides = $config->day_overrides[$templateKey] ?? [];

        foreach ($templates as $index => $phase) {
            $phaseKey = (string) $phase['phase_key'];
            $endOverride = $overrides[$phaseKey]['working_day_end'] ?? null;
            $workingDayEnd = $endOverride !== null ? (int) $endOverride : (int) $phase['working_day_end'];

            RolloutTimelinePhase::query()->create([
                'rollout_program_id' => $program->id,
                'phase_key' => $phaseKey,
                'label' => (string) $phase['label'],
                'owner_role' => $phase['owner_role'] ?? null,
                'anchor' => (string) ($phase['anchor'] ?? 'endorsement'),
                'working_day_start' => (int) $phase['working_day_start'],
                'working_day_end' => $workingDayEnd,
                'target_working_day_end' => $workingDayEnd,
                'gate_label' => isset($phase['gate']) ? (string) $phase['gate'] : null,
                'counts_toward_sla' => array_key_exists('counts_toward_sla', $phase) ? (bool) $phase['counts_toward_sla'] : true,
                'is_custom' => (bool) ($phase['is_custom'] ?? false),
                'catalog_phase_id' => isset($phase['catalog_phase_id']) ? (string) $phase['catalog_phase_id'] : null,
                'sort_order' => $index,
            ]);
        }

        $this->slaRecalculation->recalculateProgram($program->fresh(['timelinePhases']));
    }

    private function ensureProfitabilityShell(RolloutProgram $program): void
    {
        SiteProfitabilityRecord::query()->firstOrCreate(
            ['rollout_program_id' => $program->id],
            [
                'baseline' => [
                    'saq' => 0,
                    'engineering' => 0,
                    'permitting' => 0,
                    'cme' => 0,
                    'tower_material' => 0,
                    'dc_plant' => 0,
                    'power' => 0,
                ],
                'actual' => [
                    'saq' => 0,
                    'engineering' => 0,
                    'permitting' => 0,
                    'cme' => 0,
                    'tower_material' => 0,
                    'dc_plant' => 0,
                    'power' => 0,
                ],
                'profitability_status' => 'on_track',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function generateRolloutRef(array $input): string
    {
        $mno = strtoupper(substr((string) ($input['mno'] ?? 'MNO'), 0, 3));
        $year = now()->format('Y');

        return sprintf('RP-%s-%s-%s', $year, $mno, strtoupper(Str::random(4)));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function generateBatchRef(array $input): string
    {
        $mno = strtoupper(substr((string) ($input['mno'] ?? 'MNO'), 0, 3));
        $year = now()->format('Y');

        return sprintf('BATCH-%s-%s-%s', $year, $mno, strtoupper(Str::random(4)));
    }

    private function assertProjectSiteMatch(RolloutProgram $program, ?string $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        $project = Project::query()->find($projectId);
        if ($project === null) {
            return;
        }

        if ($program->site_id !== null && $project->site_id !== null && $program->site_id !== $project->site_id) {
            throw ValidationException::withMessages([
                'project_id' => [__('Rollout site must match the linked project site.')],
            ]);
        }
    }

    public function issueTcoSiteId(RolloutProgram $program, string $tenantSequencePrefix): RolloutProgram
    {
        $program = $this->canonicalSites->issueTcoSiteId($program, $tenantSequencePrefix);
        $this->canonicalSites->ensureForProgram($program);

        return $program->fresh(['site']);
    }

    private function assertProgramEditable(RolloutProgram $program): void
    {
        if (in_array($program->status, ['completed', 'cancelled', 'batch'], true)) {
            throw ValidationException::withMessages([
                'rollout' => [__('This rollout cannot be edited.')],
            ]);
        }
    }

    private function resolveCentralTenant(): ?Tenant
    {
        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null || $tenantKey === '') {
            return null;
        }

        /** @var Tenant|null $central */
        $central = Tenant::query()->find((string) $tenantKey);

        return $central;
    }
}
