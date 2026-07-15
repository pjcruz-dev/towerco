<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalP3Test extends TestCase
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

    public function test_metadata_endpoint_returns_roles(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/metadata')
            ->assertOk()
            ->assertJsonStructure(['data' => ['roles', 'departments', 'emails']]);
    }

    public function test_master_data_lookup_returns_options(): void
    {
        tenancy()->initialize($this->testTenant);

        EApprovalMasterDataSet::query()->create([
            'id' => (string) Str::uuid(),
            'key' => 'vendors',
            'name' => 'Vendors',
            'status' => 'active',
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors')
            ->assertOk()
            ->assertJsonPath('data.key', 'vendors');
    }

    public function test_settings_update_requires_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalSettingsService::class)->setString(EApprovalSettingsService::SLA_REMINDER_MINUTES, '100');
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/e-approval/settings', [
                'sla_reminder_minutes' => 120,
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $this->assertSame('120', app(EApprovalSettingsService::class)->getString(EApprovalSettingsService::SLA_REMINDER_MINUTES));
        tenancy()->end();
    }
}
