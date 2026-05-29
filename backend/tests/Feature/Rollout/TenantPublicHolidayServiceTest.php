<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TenantPublicHolidayServiceTest extends TestCase
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

        Schema::connection('tenant')->create('tenant_public_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('region', 64)->nullable();
            $table->unsignedSmallInteger('calendar_year');
            $table->timestamps();
            $table->unique(['holiday_date', 'region']);
        });
    }

    public function test_lists_holidays_for_calendar_year(): void
    {
        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-05-01',
            'name' => 'Labor Day',
            'region' => null,
            'calendar_year' => 2026,
        ]);
        TenantPublicHoliday::query()->create([
            'holiday_date' => '2025-12-25',
            'name' => 'Christmas',
            'region' => null,
            'calendar_year' => 2025,
        ]);

        $service = app(TenantPublicHolidayService::class);

        $this->assertCount(1, $service->listForYear(2026));
        $this->assertSame(['2026-05-01'], $service->activeHolidayDates(2026));
    }

    public function test_creates_updates_and_deletes_custom_holiday(): void
    {
        $service = app(TenantPublicHolidayService::class);

        $holiday = $service->create([
            'holiday_date' => '2026-08-21',
            'name' => 'Ninoy Aquino Day',
        ]);

        $this->assertSame('2026-08-21', $holiday->holiday_date->toDateString());

        $updated = $service->update($holiday, ['name' => 'Ninoy Aquino Day (special)']);
        $this->assertSame('Ninoy Aquino Day (special)', $updated->name);

        $service->delete($updated);
        $this->assertSame([], $service->listForYear(2026));
    }

    public function test_seeds_philippines_catalog_for_year(): void
    {
        $service = app(TenantPublicHolidayService::class);

        $count = $service->seedPhilippinesYear(2026);

        $this->assertGreaterThan(10, $count);
        $this->assertNotEmpty($service->listForYear(2026));
        $this->assertContains('2026-05-01', $service->activeHolidayDates(2026));
    }
}
