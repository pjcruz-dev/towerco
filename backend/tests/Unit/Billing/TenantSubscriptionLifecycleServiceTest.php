<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantSubscriptionLifecycleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class TenantSubscriptionLifecycleServiceTest extends TestCase
{
    private TenantSubscriptionLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantSubscriptionLifecycleService::class);
    }

    public function test_active_subscription_allows_full_access(): void
    {
        $tenant = $this->makeTenant(['subscription_status' => 'active']);

        $snapshot = $this->service->snapshot($tenant);

        $this->assertSame('active', $snapshot['status']);
        $this->assertSame('full', $snapshot['access_mode']);
        $this->assertTrue($snapshot['access_allowed']);
    }

    public function test_past_due_within_grace_allows_access_with_grace_mode(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => 'past_due',
            'past_due_grace_ends_at' => now()->addDays(3),
        ]);

        $snapshot = $this->service->snapshot($tenant);

        $this->assertSame('past_due', $snapshot['status']);
        $this->assertSame('grace', $snapshot['access_mode']);
        $this->assertTrue($snapshot['access_allowed']);
        $this->assertNotNull($snapshot['message']);
    }

    public function test_past_due_after_grace_is_blocked(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => 'past_due',
            'past_due_grace_ends_at' => now()->subDay(),
        ]);

        $snapshot = $this->service->snapshot($tenant);

        $this->assertSame('blocked', $snapshot['access_mode']);
        $this->assertFalse($snapshot['access_allowed']);
    }

    public function test_canceled_subscription_is_blocked(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => 'canceled',
            'canceled_at' => now(),
            'subscription_locked_at' => now(),
        ]);

        $snapshot = $this->service->snapshot($tenant);

        $this->assertSame('blocked', $snapshot['access_mode']);
        $this->assertFalse($snapshot['access_allowed']);
    }

    public function test_expired_trial_snapshot_uses_on_trial_expire_status(): void
    {
        config(['billing.subscription.on_trial_expire' => 'past_due']);

        $tenant = $this->makeTenant([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $snapshot = $this->service->snapshot($tenant);

        $this->assertSame('past_due', $snapshot['status']);
        $this->assertSame('grace', $snapshot['access_mode']);
    }

    public function test_apply_platform_update_sets_trial_end_from_config(): void
    {
        config(['billing.subscription.trial_days' => 21]);

        $tenant = $this->makeTenant(['subscription_status' => 'active']);
        Carbon::setTestNow('2026-06-01 00:00:00');

        $this->service->applyPlatformUpdate($tenant, ['subscription_status' => 'trial']);

        $this->assertSame('trial', $tenant->subscription_status);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->equalTo(Carbon::parse('2026-06-22 00:00:00')));

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTenant(array $attributes): Tenant
    {
        $tenant = new Tenant(array_merge([
            'id' => 'lifecycle-unit-test',
            'plan_tier' => 'starter',
            'seat_limit' => 25,
        ], $attributes));
        $tenant->exists = true;

        return $tenant;
    }
}
