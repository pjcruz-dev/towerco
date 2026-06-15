<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutMilestoneCycleApiTest extends TestCase
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

    public function test_rollout_detail_exposes_derived_milestone_cycles(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Milestone API ring',
                'endorsement_date' => '2026-04-01',
            ]);

        $create->assertCreated();
        $rolloutId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rolloutId.'/tssr-approved', [
                'tssr_approved_date' => '2026-04-28',
            ])
            ->assertOk();

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rolloutId);

        $show->assertOk()
            ->assertJsonPath('data.milestone_cycles_summary.total', 19)
            ->assertJsonCount(19, 'data.milestone_cycles')
            ->assertJsonPath('data.milestone_cycles', fn ($cycles) => collect($cycles)->contains(
                fn ($row) => ($row['phase_key'] ?? '') === 'site_hunting'
                    && ($row['anchor'] ?? '') === 'endorsement',
            ));

        $moc = collect($show->json('data.milestone_cycles'))->firstWhere('phase_key', 'moc_securing');
        $this->assertNotNull($moc);
        $this->assertSame('day_one', $moc['anchor'] ?? null);
    }

    public function test_rollout_detail_includes_custom_milestone_when_in_snapshot(): void
    {
        tenancy()->initialize($this->testTenant);

        $snapshot = RolloutPlaybookV2Definition::payload();
        $timeline = $snapshot['timeline_templates']['bts'];

        foreach ($timeline as $index => $phase) {
            if (($phase['phase_key'] ?? '') === 'moc_col') {
                array_splice($timeline, $index, 0, [[
                    'phase_key' => 'lgu_clearance',
                    'label' => 'LGU Clearance',
                    'owner_role' => 'saq',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 1,
                    'working_day_end' => 4,
                    'is_custom' => true,
                    'counts_toward_sla' => true,
                ]]);
                break;
            }
        }

        $snapshot['timeline_templates']['bts'] = $timeline;
        $snapshot['milestone_derived_from_timeline'] = true;

        TenantRolloutPlaybookConfig::query()->firstOrFail()->update([
            'playbook_snapshot' => $snapshot,
        ]);

        tenancy()->end();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Custom milestone ring',
                'endorsement_date' => '2026-04-01',
            ]);

        $rolloutId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rolloutId.'/tssr-approved', [
                'tssr_approved_date' => '2026-04-28',
            ]);

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rolloutId);

        $show->assertOk()
            ->assertJsonPath('data.milestone_cycles', fn ($cycles) => collect($cycles)->contains(
                fn ($row) => ($row['phase_key'] ?? '') === 'lgu_clearance'
                    && ($row['is_custom'] ?? false) === true
                    && ($row['anchor'] ?? '') === 'day_one',
            ));
    }
}
