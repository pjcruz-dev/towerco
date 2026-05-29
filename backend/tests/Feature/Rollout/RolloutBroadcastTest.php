<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Events\RolloutUpdated;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Support\Facades\Event;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutBroadcastTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
    }

    public function test_gate_update_dispatches_rollout_updated_broadcast(): void
    {
        Event::fake([RolloutUpdated::class]);

        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-BROADCAST',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'permitting',
            'sla_working_days' => 115,
        ]);

        /** @var RolloutTimelinePhase $phase */
        $phase = RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $rollout->id,
            'phase_key' => 'site_hunting',
            'label' => 'Site Hunting',
            'anchor' => 'endorsement',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'gate_status' => 'pending',
            'sort_order' => 1,
        ]);

        app(RolloutProgramService::class)->updatePhaseGateStatus($phase, 'passed');

        tenancy()->end();

        Event::assertDispatched(RolloutUpdated::class, function (RolloutUpdated $event) use ($rollout): bool {
            return $event->rolloutId === $rollout->id && $event->reason === 'rollout.gate_updated';
        });
    }
}
