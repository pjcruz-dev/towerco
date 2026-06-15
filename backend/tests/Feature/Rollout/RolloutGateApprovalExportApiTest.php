<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutGateApprovalExportApiTest extends TestCase
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
        $this->seedGateApprovalRow();
    }

    public function test_export_returns_csv_with_header_row(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/project-one/gate-approvals/export?status=all');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('request_id', $csv);
        $this->assertStringContainsString('rollout_ref', $csv);
        $this->assertStringContainsString('RP-EXPORT-TEST', $csv);
    }

    private function seedGateApprovalRow(): void
    {
        tenancy()->initialize($this->testTenant);

        $program = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-EXPORT-TEST',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);

        $phase = RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'site_hunting',
            'label' => 'Site hunting',
            'owner_role' => 'saq',
            'anchor' => 'endorsement',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'gate_label' => 'Test gate',
            'gate_status' => 'pending',
            'sort_order' => 1,
        ]);

        RolloutGateApprovalRequest::query()->create([
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
            'current_step_started_at' => now(),
        ]);

        tenancy()->end();
    }
}
