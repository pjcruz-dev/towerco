<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RegionAwareWorkingDaysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('tenant')->create('tenant_public_holidays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('region', 64)->nullable();
            $table->unsignedSmallInteger('calendar_year');
            $table->timestamps();
        });
    }

    public function test_national_holiday_applies_to_all_rollout_regions(): void
    {
        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-06-12',
            'name' => 'Independence Day',
            'region' => null,
            'calendar_year' => 2026,
        ]);

        $factory = app(TenantWorkingDaysCalendarFactory::class);

        $this->assertContains('2026-06-12', $factory->activeHolidayDates('ncr', 2026));
        $this->assertContains('2026-06-12', $factory->activeHolidayDates('visayas', 2026));
        $this->assertContains('2026-06-12', $factory->activeHolidayDates(null, 2026));
    }

    public function test_regional_holiday_applies_only_to_matching_rollout_region(): void
    {
        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-06-12',
            'name' => 'NCR special non-working day',
            'region' => 'ncr',
            'calendar_year' => 2026,
        ]);

        $factory = app(TenantWorkingDaysCalendarFactory::class);

        $this->assertContains('2026-06-12', $factory->activeHolidayDates('ncr', 2026));
        $this->assertNotContains('2026-06-12', $factory->activeHolidayDates('visayas', 2026));
        $this->assertNotContains('2026-06-12', $factory->activeHolidayDates(null, 2026));
    }

    public function test_holiday_scope_label_describes_region_policy(): void
    {
        $factory = app(TenantWorkingDaysCalendarFactory::class);

        $this->assertSame('National only', $factory->holidayScopeLabel(null));
        $this->assertSame('NCR + national', $factory->holidayScopeLabel('ncr'));
    }
}
