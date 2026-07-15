<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutGateApprovalInboxTest extends TestCase
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

    public function test_awaiting_me_inbox_lists_open_gate_for_tenant_admin(): void
    {
        [, $phase] = $this->seedRolloutWithPhase('site_hunting');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/project-one/rollout-phases/{$phase->id}/gate-approvals", [
                'request_notes' => 'Ready for SAQ review',
            ])
            ->assertOk();

        $inbox = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/gate-approvals?awaiting_me=1');

        $inbox->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.current_approver_role', 'saq')
            ->assertJsonPath('data.data.0.can_act', true);
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
            'rollout_ref' => 'RP-INBOX-'.uniqid('', true),
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'search_ring_name' => 'Inbox test ring',
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

        RolloutGateApprovalRequest::query()->where('rollout_timeline_phase_id', $phase->id)->delete();

        tenancy()->end();

        return [$program, $phase];
    }
}
