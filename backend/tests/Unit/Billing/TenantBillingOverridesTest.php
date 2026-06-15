<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use App\Modules\Billing\Support\TenantBillingOverridesValidator;
use Tests\TestCase;

final class TenantBillingOverridesTest extends TestCase
{
    public function test_enterprise_override_unlimited_file_fields(): void
    {
        $tenant = new Tenant([
            'id' => 'override-test',
            'plan_tier' => 'enterprise',
            'billing_overrides' => TenantBillingOverridesValidator::validate([
                'seat_limit' => 500,
                'modules' => [
                    'e_approval' => [
                        'file_uploads' => true,
                        'max_file_fields' => null,
                    ],
                ],
            ]),
        ]);
        $tenant->exists = true;

        $service = app(TenantPlanEntitlementsService::class);
        $entitlements = $service->forTenant($tenant);
        $ea = $entitlements['modules']['e_approval'];

        $this->assertTrue($ea['file_uploads']);
        $this->assertNull($ea['max_file_fields']);
        $this->assertSame(500, $service->effectiveSeatLimit($tenant));
    }

    public function test_starter_override_can_enable_file_uploads(): void
    {
        $tenant = new Tenant([
            'id' => 'starter-override',
            'plan_tier' => 'starter',
            'billing_overrides' => [
                'modules' => [
                    'e_approval' => ['file_uploads' => true, 'max_file_fields' => 5],
                ],
            ],
        ]);
        $tenant->exists = true;

        $ea = app(TenantPlanEntitlementsService::class)->forTenant($tenant)['modules']['e_approval'];

        $this->assertTrue($ea['file_uploads']);
        $this->assertSame(5, $ea['max_file_fields']);
    }
}
