<?php

declare(strict_types=1);

namespace Tests\Unit\AdminOne;

use App\Modules\AdminOne\Services\TenantSeatLimitService;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantSeatLimitServiceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_create_paid_user_blocked_when_seat_limit_reached(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->testTenant->seat_limit = 1;
        $this->testTenant->save();
        tenancy()->end();

        tenancy()->initialize($this->testTenant);

        $service = app(TenantUserAdminService::class);

        $this->expectException(ValidationException::class);
        $service->create('Extra Manager', 'manager@test.localhost', ['manager']);

        tenancy()->end();
    }

    public function test_viewer_does_not_count_toward_seat_limit(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->testTenant->seat_limit = 1;
        $this->testTenant->save();
        tenancy()->end();

        tenancy()->initialize($this->testTenant);

        $service = app(TenantUserAdminService::class);
        $service->create('Read Only', 'viewer@test.localhost', ['viewer']);

        $seats = app(TenantSeatLimitService::class);
        $this->assertSame(1, $seats->activeSeatCount());
        $this->assertSame(0, $seats->seatsAvailable());

        tenancy()->end();
    }

    public function test_active_seat_count_excludes_inactive_users(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Inactive',
            'email' => 'inactive@test.localhost',
            'password' => 'x',
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        $count = app(TenantSeatLimitService::class)->activeSeatCount();

        $this->assertSame(1, $count);

        tenancy()->end();
    }
}
