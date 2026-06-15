<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Notifications\RolloutGateApprovalNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutGateApprovalEscalationTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.gate_approval_mail_mailer' => 'array',
            'mail.default' => 'array',
            'cache.default' => 'array',
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
    }

    public function test_escalate_command_notifies_and_records_last_escalated_at(): void
    {
        Notification::fake();

        [$requestId, $approver] = $this->seedOverdueGateApproval();

        $this->artisan('rollout:gate-approvals:escalate', [
            '--tenants' => [(string) $this->testTenant->id],
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);
        $fresh = RolloutGateApprovalRequest::query()->findOrFail($requestId);
        $this->assertNotNull($fresh->last_escalated_at);
        tenancy()->end();

        Notification::assertSentTo($approver, RolloutGateApprovalNotification::class);
    }

    public function test_escalate_skips_when_step_not_past_threshold(): void
    {
        Notification::fake();

        tenancy()->initialize($this->testTenant);

        TenantRolloutPlaybookConfig::query()->firstOrFail()->update([
            'gate_approval_escalation_working_days' => 3,
        ]);

        /** @var TenantUser $approver */
        $approver = TenantUser::query()->firstOrFail();
        $program = $this->createProgram($approver);
        $phase = $this->createPhase($program);
        $request = RolloutGateApprovalRequest::query()->create([
            'rollout_program_id' => $program->id,
            'rollout_timeline_phase_id' => $phase->id,
            'phase_key' => $phase->phase_key,
            'gate_label' => $phase->gate_label,
            'status' => RolloutGateApprovalRequest::STATUS_IN_REVIEW,
            'current_step' => 0,
            'approval_chain' => ['saq'],
            'step_log' => [],
            'requested_by_id' => TenantUser::query()->value('id'),
            'submitted_at' => now(),
            'current_step_started_at' => Carbon::today()->subWeekday(),
        ]);

        tenancy()->end();

        $this->artisan('rollout:gate-approvals:escalate', [
            '--tenants' => [(string) $this->testTenant->id],
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);
        $fresh = RolloutGateApprovalRequest::query()->findOrFail($request->id);
        $this->assertNull($fresh->last_escalated_at);
        tenancy()->end();

        Notification::assertNothingSent();
    }

    /**
     * @return array{0: string, 1: TenantUser}
     */
    private function seedOverdueGateApproval(): array
    {
        tenancy()->initialize($this->testTenant);

        /** @var TenantUser $approver */
        $approver = TenantUser::query()->firstOrFail();

        TenantRolloutPlaybookConfig::query()->firstOrFail()->update([
            'gate_approval_escalation_working_days' => 3,
        ]);

        $program = $this->createProgram($approver);
        $phase = $this->createPhase($program);

        $request = RolloutGateApprovalRequest::query()->create([
            'rollout_program_id' => $program->id,
            'rollout_timeline_phase_id' => $phase->id,
            'phase_key' => $phase->phase_key,
            'gate_label' => $phase->gate_label,
            'status' => RolloutGateApprovalRequest::STATUS_IN_REVIEW,
            'current_step' => 0,
            'approval_chain' => ['saq'],
            'step_log' => [],
            'requested_by_id' => TenantUser::query()->value('id'),
            'submitted_at' => now()->subDays(30),
            'current_step_started_at' => Carbon::parse('2026-01-02'),
        ]);

        tenancy()->end();

        return [(string) $request->id, $approver];
    }

    private function createProgram(TenantUser $saqOwner): RolloutProgram
    {
        return RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-ESC-'.uniqid('', true),
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'search_ring_name' => 'Escalation test',
            'saq_owner_id' => $saqOwner->id,
        ]);
    }

    private function createPhase(RolloutProgram $program): RolloutTimelinePhase
    {
        return RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'site_hunting',
            'label' => 'Site hunting',
            'owner_role' => 'saq',
            'anchor' => 'endorsement',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'gate_label' => 'Candidates uploaded',
            'gate_status' => 'pending',
            'sort_order' => 1,
        ]);
    }
}
