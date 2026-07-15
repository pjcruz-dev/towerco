<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Sites\Models\Site;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutCanonicalSiteTest extends TestCase
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

    public function test_rollout_create_provisions_tco_site_id_and_canonical_site(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Registry ring',
                'region' => 'ncr',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.tco_site_id', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.site_id', fn ($value) => is_string($value) && $value !== '');

        tenancy()->initialize($this->testTenant);

        $rollout = RolloutProgram::query()->findOrFail($create->json('data.id'));
        $this->assertNotNull($rollout->tco_site_id);
        $this->assertNotNull($rollout->site_id);

        $site = Site::query()->findOrFail($rollout->site_id);
        $this->assertSame($rollout->tco_site_id, $site->site_code);
        $this->assertSame('Registry ring', $site->name);
        $this->assertSame('site_acquisition', $site->status);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/sites?search='.$rollout->tco_site_id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.site_code', $rollout->tco_site_id);
    }

    public function test_candidate_selection_updates_existing_canonical_site_coordinates(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Coordinate ring',
                'region' => 'ncr',
            ]);

        $rolloutId = $create->json('data.id');
        $siteId = $create->json('data.site_id');

        $candidate = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rolloutId.'/candidates', [
                'label' => 'Winning candidate',
                'latitude' => 14.5995,
                'longitude' => 120.9842,
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/candidates/'.$candidate->json('data.id').'/select')
            ->assertOk();

        tenancy()->initialize($this->testTenant);

        $site = Site::query()->findOrFail($siteId);
        $this->assertSame('Winning candidate', $site->name);
        $this->assertSame('14.59950000', (string) $site->latitude);
        $this->assertSame('120.98420000', (string) $site->longitude);
        $this->assertSame('under_construction', $site->status);

        tenancy()->end();
    }
}
