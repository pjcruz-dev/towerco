<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutHolidaySlaIntegrationTest extends TestCase
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

    public function test_adding_holiday_via_api_shifts_target_rfi_after_sla_recalc(): void
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-HOLIDAY-API',
            'mno' => 'globe',
            'project_type' => 'bts',
            'region' => 'ncr',
            'status' => 'permitting',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 5,
            'target_rfi_working_date' => '2026-05-05',
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/public-holidays', [
                'holiday_date' => '2026-05-01',
                'name' => 'Labor Day',
                'region' => null,
            ])
            ->assertCreated();

        $this->artisan('tenants:recalculate-rollout-slas', [
            '--tenant' => $this->testTenant->id,
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);
        $rollout->refresh();
        $this->assertSame('2026-05-06', $rollout->target_rfi_working_date?->toDateString());
        $this->assertSame(1, TenantPublicHoliday::query()->whereDate('holiday_date', '2026-05-01')->count());
        tenancy()->end();
    }
}
