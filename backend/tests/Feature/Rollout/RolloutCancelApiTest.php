<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutCancelApiTest extends TestCase
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
    }

    public function test_cancel_rollout_sets_status_and_reason(): void
    {
        $rollout = $this->seedRollout('permitting');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/cancel', [
                'cancellation_reason' => 'MNO withdrew endorsement',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'MNO withdrew endorsement');

        tenancy()->initialize($this->testTenant);
        $fresh = RolloutProgram::query()->findOrFail($rollout->id);
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        tenancy()->end();
    }

    public function test_cancel_completed_rollout_is_rejected(): void
    {
        $rollout = $this->seedRollout('completed');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/cancel', [
                'cancellation_reason' => 'Too late',
            ]);

        $response->assertStatus(422);
    }

    public function test_patch_rollout_updates_metadata_fields(): void
    {
        $rollout = $this->seedRollout('saq');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollouts/'.$rollout->id, [
                'search_ring_name' => 'Updated ring',
                'region' => 'visayas',
                'endorsement_ref' => 'END-2026-99',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.search_ring_name', 'Updated ring')
            ->assertJsonPath('data.region', 'visayas')
            ->assertJsonPath('data.endorsement_ref', 'END-2026-99');
    }

    private function seedRollout(string $status): RolloutProgram
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-CANCEL-TEST',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => $status,
            'search_ring_name' => 'Test ring',
            'region' => 'ncr',
            'sla_working_days' => 115,
            'actual_rfi_date' => $status === 'completed' ? '2026-05-01' : null,
        ]);

        tenancy()->end();

        return $rollout;
    }
}
