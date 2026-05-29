<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteProfitabilityRecord;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutProfitabilityRbacTest extends TestCase
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

    public function test_viewer_receives_summary_only_profitability_payload(): void
    {
        [$rollout, $viewer] = $this->seedRolloutWithProfitability();

        $response = $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rollout->id.'/profitability');

        $response->assertOk()
            ->assertJsonPath('data.access', 'summary_only')
            ->assertJsonMissingPath('data.baseline_total');
    }

    public function test_finance_role_receives_full_profitability_payload(): void
    {
        [$rollout, , $financeUser] = $this->seedRolloutWithProfitability();

        $response = $this->actingAs($financeUser, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rollout->id.'/profitability');

        $response->assertOk()
            ->assertJsonPath('data.baseline.saq', 100000)
            ->assertJsonPath('data.actual.cme', 50000)
            ->assertJsonStructure(['data' => ['baseline_total', 'actual_total']]);
    }

    public function test_viewer_cannot_patch_profitability(): void
    {
        [$rollout, $viewer] = $this->seedRolloutWithProfitability();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollouts/'.$rollout->id.'/profitability', [
                'profitability_status' => 'at_loss',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: RolloutProgram, 1: TenantUser, 2: TenantUser}
     */
    private function seedRolloutWithProfitability(): array
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-FIN-RBAC',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'permitting',
            'sla_working_days' => 115,
        ]);

        SiteProfitabilityRecord::query()->create([
            'rollout_program_id' => $rollout->id,
            'baseline' => ['saq' => 100000, 'cme' => 80000],
            'actual' => ['saq' => 90000, 'cme' => 50000],
            'profitability_status' => 'on_track',
        ]);

        $viewer = TenantUser::query()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@test.localhost',
            'password' => 'password',
        ]);
        $viewer->assignRole('viewer');

        $financeUser = TenantUser::query()->create([
            'name' => 'Finance User',
            'email' => 'finance@test.localhost',
            'password' => 'password',
        ]);
        $financeUser->assignRole('finance');

        tenancy()->end();

        return [$rollout, $viewer, $financeUser];
    }
}
