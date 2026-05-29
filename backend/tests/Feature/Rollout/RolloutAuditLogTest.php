<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Services\RolloutProgramService;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutAuditLogTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedPlaybookConfig();
    }

    public function test_create_rollout_writes_rollout_created_audit_event(): void
    {
        tenancy()->initialize($this->testTenant);

        $service = app(RolloutProgramService::class);
        $program = $service->create([
            'mno' => 'globe',
            'project_type' => 'bts',
            'search_ring_name' => 'Audit ring',
            'rollout_ref' => 'RP-AUDIT-CREATE',
        ]);

        $activity = Activity::query()
            ->where('log_name', 'rollout')
            ->where('event', 'rollout.created')
            ->where('subject_id', $program->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('globe', $activity->properties->get('mno'));

        tenancy()->end();
    }

    public function test_create_rollout_without_endorsement_date_leaves_field_null(): void
    {
        tenancy()->initialize($this->testTenant);

        $service = app(RolloutProgramService::class);
        $program = $service->create([
            'mno' => 'globe',
            'project_type' => 'bts',
            'search_ring_name' => 'No endorsement ring',
            'rollout_ref' => 'RP-NO-ENDORSE',
        ]);

        $this->assertNull($program->endorsement_date);
        $this->assertTrue($program->timelinePhases->isNotEmpty());
        $this->assertNull($program->timelinePhases->first()?->target_start_date);
        $this->assertNull($program->timelinePhases->first()?->target_end_date);

        tenancy()->end();
    }

    public function test_cancel_rollout_writes_rollout_cancelled_audit_event(): void
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-AUDIT-CANCEL',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $rollout->id,
            'phase_key' => 'site_hunting',
            'label' => 'Site Hunting',
            'anchor' => 'endorsement',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'sort_order' => 1,
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/cancel', [
                'cancellation_reason' => 'Duplicate endorsement',
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);

        $activity = Activity::query()
            ->where('log_name', 'rollout')
            ->where('event', 'rollout.cancelled')
            ->where('subject_id', $rollout->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('Duplicate endorsement', $activity->properties->get('reason'));

        tenancy()->end();
    }

    private function seedPlaybookConfig(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        tenancy()->end();
    }
}
