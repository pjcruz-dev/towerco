<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutBulkUpdateApiTest extends TestCase
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

    public function test_bulk_update_applies_metadata_to_multiple_rollouts(): void
    {
        $rollouts = $this->seedRollouts(2);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/bulk-update', [
                'rollout_ids' => [$rollouts[0]->id, $rollouts[1]->id],
                'updates' => [
                    'region' => 'ncr',
                    'territory' => 'north',
                    'endorsement_ref' => 'BULK-REF-2026',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.failed', 0);

        tenancy()->initialize($this->testTenant);
        foreach ($rollouts as $rollout) {
            $fresh = RolloutProgram::query()->findOrFail($rollout->id);
            $this->assertSame('ncr', $fresh->region);
            $this->assertSame('north', $fresh->territory);
            $this->assertSame('BULK-REF-2026', $fresh->endorsement_ref);
        }
        tenancy()->end();
    }

    public function test_bulk_update_skips_completed_rollouts(): void
    {
        $active = $this->seedRollouts(1)[0];
        $completed = $this->seedRollouts(1)[0];

        tenancy()->initialize($this->testTenant);
        $completed->status = 'completed';
        $completed->save();
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/bulk-update', [
                'rollout_ids' => [$active->id, $completed->id],
                'updates' => ['region' => 'visayas'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.failed', 1);
    }

    public function test_user_without_rollout_manage_cannot_bulk_update(): void
    {
        $rollout = $this->seedRollouts(1)[0];

        tenancy()->initialize($this->testTenant);
        $viewer = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@test.localhost',
            'password' => 'password',
        ]);
        $viewer->assignRole('viewer');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/bulk-update', [
                'rollout_ids' => [$rollout->id],
                'updates' => ['region' => 'ncr'],
            ])
            ->assertForbidden();
    }

    /**
     * @return list<RolloutProgram>
     */
    private function seedRollouts(int $count): array
    {
        tenancy()->initialize($this->testTenant);

        $rollouts = [];
        for ($index = 0; $index < $count; $index++) {
            $rollouts[] = RolloutProgram::query()->create([
                'playbook_version' => '2.0.0',
                'rollout_ref' => 'RP-BULK-'.uniqid('', true),
                'mno' => 'globe',
                'project_type' => 'bts',
                'status' => 'saq',
                'sla_working_days' => 115,
                'search_ring_name' => 'Ring '.$index,
            ]);
        }

        tenancy()->end();

        return $rollouts;
    }
}
