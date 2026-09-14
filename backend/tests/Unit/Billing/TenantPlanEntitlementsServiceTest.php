<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantPlanEntitlementsServiceTest extends TestCase
{
    public function test_catalog_lists_three_tiers_in_sort_order(): void
    {
        $catalog = app(TenantPlanEntitlementsService::class)->catalog();

        $this->assertCount(3, $catalog['tiers']);
        $this->assertSame('starter', $catalog['tiers'][0]['plan_tier']);
        $this->assertSame('enterprise', $catalog['tiers'][2]['plan_tier']);
    }

    public function test_starter_disallows_e_approval_file_uploads(): void
    {
        $modules = app(TenantPlanEntitlementsService::class)->forTier('starter')['modules'];
        $ea = $modules['e_approval'];

        $this->assertFalse($ea['file_uploads']);
        $this->assertSame(0, $ea['max_file_fields']);
    }

    public function test_enterprise_allows_unlimited_file_fields(): void
    {
        $modules = app(TenantPlanEntitlementsService::class)->forTier('enterprise')['modules'];
        $ea = $modules['e_approval'];

        $this->assertTrue($ea['file_uploads']);
        $this->assertNull($ea['max_file_fields']);
    }

    public function test_enterprise_e_approval_features_returns_unlimited_file_fields(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'plan_tier' => 'enterprise',
            'billing_overrides' => null,
        ]);

        $features = app(TenantPlanEntitlementsService::class)->eApprovalFeatures($tenant->id);

        $this->assertSame('enterprise', $features['plan_tier']);
        $this->assertTrue($features['file_uploads']);
        $this->assertNull($features['max_file_fields']);

        $tenant->delete();
    }

    public function test_professional_e_approval_features_returns_capped_file_fields(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'plan_tier' => 'professional',
            'billing_overrides' => null,
        ]);

        $features = app(TenantPlanEntitlementsService::class)->eApprovalFeatures($tenant->id);

        $this->assertSame('professional', $features['plan_tier']);
        $this->assertTrue($features['file_uploads']);
        $this->assertSame(10, $features['max_file_fields']);

        $tenant->delete();
    }

    public function test_detects_downgrade_rank(): void
    {
        $service = app(TenantPlanEntitlementsService::class);

        $this->assertTrue($service->isDowngrade('enterprise', 'starter'));
        $this->assertFalse($service->isDowngrade('starter', 'professional'));
    }
}
