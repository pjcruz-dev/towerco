<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AssetOne\Models\Asset;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\TowerOne\Models\Tower;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class InfrastructureShowApiTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'sites',
                'tower_one',
                'asset_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'INF-001',
            'name' => 'Infrastructure Test Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_site_show_returns_detail(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/sites/'.$this->site->id)
            ->assertOk()
            ->assertJsonPath('data.site_code', 'INF-001')
            ->assertJsonPath('data.name', 'Infrastructure Test Site');
    }

    public function test_tower_show_returns_detail(): void
    {
        tenancy()->initialize($this->testTenant);
        $tower = Tower::query()->create([
            'site_id' => $this->site->id,
            'tower_type' => 'monopole',
            'status' => 'active',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/tower-one/towers/'.$tower->id)
            ->assertOk()
            ->assertJsonPath('data.tower_type', 'monopole')
            ->assertJsonPath('data.site.site_code', 'INF-001');
    }

    public function test_asset_show_returns_detail(): void
    {
        tenancy()->initialize($this->testTenant);
        $asset = Asset::query()->create([
            'asset_code' => 'AST-P3',
            'name' => 'Test Generator',
            'category' => 'power',
            'status' => 'in_service',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/asset-one/assets/'.$asset->id)
            ->assertOk()
            ->assertJsonPath('data.asset_code', 'AST-P3')
            ->assertJsonPath('data.name', 'Test Generator');
    }
}
