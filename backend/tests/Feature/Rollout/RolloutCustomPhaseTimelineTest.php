<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutCustomPhaseTimelineTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    private string $catalogPhaseId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
        $this->seedPlaybookWithCustomPhase();
    }

    public function test_new_rollout_instantiates_custom_phase_metadata(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'LGU clearance ring',
                'endorsement_date' => '2026-04-01',
            ]);

        $create->assertCreated();
        $rolloutId = (string) $create->json('data.id');

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rolloutId);

        $show->assertOk()
            ->assertJsonPath('data.timeline_phases', fn ($phases) => collect($phases)->contains(
                fn ($phase) => $phase['phase_key'] === 'lgu_clearance'
                    && $phase['is_custom'] === true
                    && $phase['counts_toward_sla'] === false
                    && $phase['label'] === 'LGU Clearance',
            ));

        tenancy()->initialize($this->testTenant);
        $dbPhase = RolloutTimelinePhase::query()
            ->where('rollout_program_id', $rolloutId)
            ->where('phase_key', 'lgu_clearance')
            ->first();
        tenancy()->end();

        $this->assertNotNull($dbPhase);
        $this->assertTrue($dbPhase->is_custom);
        $this->assertFalse($dbPhase->counts_toward_sla);
        $this->assertSame($this->catalogPhaseId, (string) $dbPhase->catalog_phase_id);
    }

    private function seedPlaybookWithCustomPhase(): void
    {
        $this->catalogPhaseId = (string) Str::uuid();

        tenancy()->initialize($this->testTenant);

        $config = TenantRolloutPlaybookConfig::query()->firstOrFail();
        $snapshot = $config->playbook_snapshot;
        $bts = $snapshot['timeline_templates']['bts'] ?? [];
        $insertAt = 3;

        $custom = [
            'phase_key' => 'lgu_clearance',
            'label' => 'LGU Clearance',
            'owner_role' => 'saq',
            'anchor' => 'tssr_approved',
            'working_day_start' => 10,
            'working_day_end' => 14,
            'gate' => 'Mayor approval',
            'counts_toward_sla' => false,
            'is_custom' => true,
            'catalog_phase_id' => $this->catalogPhaseId,
        ];

        array_splice($bts, $insertAt, 0, [$custom]);
        $snapshot['timeline_templates']['bts'] = $bts;
        $config->update(['playbook_snapshot' => $snapshot]);

        tenancy()->end();
    }
}
