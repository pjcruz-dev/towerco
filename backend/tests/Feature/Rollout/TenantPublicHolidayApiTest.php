<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantPublicHolidayApiTest extends TestCase
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

    public function test_index_lists_holidays_for_year(): void
    {
        tenancy()->initialize($this->testTenant);
        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-05-01',
            'name' => 'Labor Day',
            'region' => null,
            'calendar_year' => 2026,
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/public-holidays?year=2026');

        $response->assertOk()
            ->assertJsonPath('data.year', 2026)
            ->assertJsonCount(1, 'data.holidays')
            ->assertJsonPath('data.holidays.0.name', 'Labor Day');
    }

    public function test_store_creates_custom_holiday(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/public-holidays', [
                'holiday_date' => '2026-12-24',
                'name' => 'Christmas Eve',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.holiday_date', '2026-12-24')
            ->assertJsonPath('data.name', 'Christmas Eve');
    }

    public function test_destroy_removes_holiday(): void
    {
        tenancy()->initialize($this->testTenant);
        $holiday = TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-08-21',
            'name' => 'Ninoy Aquino Day',
            'region' => null,
            'calendar_year' => 2026,
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/project-one/public-holidays/'.$holiday->id);

        $response->assertNoContent();
    }

    public function test_seed_philippines_populates_catalog(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/public-holidays/seed-philippines', [
                'year' => 2026,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.year', 2026);

        $this->assertGreaterThan(10, $response->json('data.seeded_count'));
    }
}
