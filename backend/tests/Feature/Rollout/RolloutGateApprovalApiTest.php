<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutGateApprovalApiTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
    }

    public function test_submit_and_approve_gate_advances_chain_and_passes_gate(): void
    {
        [$program, $phase] = $this->seedRolloutWithPhase('site_hunting');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/project-one/rollout-phases/{$phase->id}/gate-approvals", [
                'request_notes' => 'Three candidates uploaded',
            ]);

        $submit->assertOk()
            ->assertJsonPath('data.approval.status', 'in_review')
            ->assertJsonPath('data.approval.current_approver_role', 'saq');

        $approvalId = $submit->json('data.approval.id');

        $approveSaq = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/project-one/gate-approvals/{$approvalId}/decide", [
                'decision' => 'approve',
            ]);

        $approveSaq->assertOk()
            ->assertJsonPath('data.approval.status', 'in_review')
            ->assertJsonPath('data.approval.current_approver_role', 'pmo');

        $approvePmo = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/project-one/gate-approvals/{$approvalId}/decide", [
                'decision' => 'approve',
            ]);

        $approvePmo->assertOk()
            ->assertJsonPath('data.approval.status', 'approved');

        tenancy()->initialize($this->testTenant);
        $freshPhase = RolloutTimelinePhase::query()->findOrFail($phase->id);
        $this->assertSame('passed', $freshPhase->gate_status);
        tenancy()->end();
    }

    public function test_reject_keeps_gate_pending_for_resubmit(): void
    {
        [, $phase] = $this->seedRolloutWithPhase('tssr_creation');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/project-one/rollout-phases/{$phase->id}/gate-approvals", []);

        $approvalId = $submit->json('data.approval.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/project-one/gate-approvals/{$approvalId}/decide", [
                'decision' => 'reject',
                'notes' => 'Missing engineering sign-off',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval.status', 'rejected');

        tenancy()->initialize($this->testTenant);
        $freshPhase = RolloutTimelinePhase::query()->findOrFail($phase->id);
        $this->assertSame('pending', $freshPhase->gate_status);
        tenancy()->end();
    }

    public function test_cannot_mark_passed_without_approval_when_policy_enabled(): void
    {
        [, $phase] = $this->seedRolloutWithPhase('permitting');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/project-one/rollout-phases/{$phase->id}/gate", [
                'gate_status' => 'passed',
            ])
            ->assertUnprocessable();
    }

    /**
     * @return array{0: RolloutProgram, 1: RolloutTimelinePhase}
     */
    private function seedRolloutWithPhase(string $phaseKey): array
    {
        tenancy()->initialize($this->testTenant);

        TenantRolloutPlaybookConfig::query()->first()?->update([
            'gate_approval_policies' => null,
        ]);

        /** @var RolloutProgram $program */
        $program = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-GATE-'.uniqid('', true),
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'search_ring_name' => 'Gate test ring',
        ]);

        /** @var RolloutTimelinePhase $phase */
        $phase = RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => $phaseKey,
            'label' => ucfirst(str_replace('_', ' ', $phaseKey)),
            'owner_role' => 'saq',
            'anchor' => 'endorsement',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'gate_label' => 'Test gate',
            'gate_status' => 'pending',
            'sort_order' => 1,
        ]);

        tenancy()->end();

        return [$program, $phase];
    }
}
