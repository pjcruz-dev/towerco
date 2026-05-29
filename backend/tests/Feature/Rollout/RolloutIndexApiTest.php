<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutIndexApiTest extends TestCase
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
        $this->seedRollouts();
    }

    public function test_index_returns_paginated_payload_with_meta(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts?page=1&per_page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status_and_mno(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts?status=permitting&mno=globe');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.rollout_ref', 'RP-PERM-GLOBE');
    }

    public function test_index_search_matches_rollout_ref(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts?search=SAQ-RING');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'saq');
    }

    private function seedRollouts(): void
    {
        tenancy()->initialize($this->testTenant);

        RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-SAQ-RING',
            'mno' => 'smart',
            'project_type' => 'bts',
            'status' => 'saq',
            'search_ring_name' => 'Makati Ring A',
            'region' => 'ncr',
            'sla_working_days' => 115,
        ]);

        RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-PERM-GLOBE',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'permitting',
            'search_ring_name' => 'Quezon Ring B',
            'region' => 'ncr',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 115,
        ]);

        RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-COMPLETE',
            'mno' => 'dito',
            'project_type' => 'rtb',
            'status' => 'completed',
            'region' => 'visayas',
            'actual_rfi_date' => '2026-05-01',
            'sla_working_days' => 85,
        ]);

        tenancy()->end();
    }
}
