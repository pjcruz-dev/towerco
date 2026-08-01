<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutGeographyLookup;
use App\Modules\Rollout\Services\RolloutGeographyLookupService;
use App\Modules\Rollout\Support\PhilippinesRolloutGeographyCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutGeographyLookupServiceTest extends TestCase
{
    private RolloutGeographyLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('tenant')->create('rollout_geography_lookups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 32);
            $table->string('code', 32);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'code']);
        });

        $this->service = new RolloutGeographyLookupService;
    }

    public function test_catalog_includes_regions_and_territories(): void
    {
        $defaults = PhilippinesRolloutGeographyCatalog::defaults();
        $kinds = array_unique(array_column($defaults, 'kind'));
        $codes = array_column($defaults, 'code');

        $this->assertContains('region', $kinds);
        $this->assertContains('territory', $kinds);
        $this->assertContains('13', $codes);
        $this->assertContains('NCR', $codes);
        $this->assertContains('NLZ', $codes);
    }

    public function test_seed_defaults_creates_missing_rows_only(): void
    {
        $first = $this->service->seedDefaults();
        $this->assertGreaterThan(0, $first['created']);

        $second = $this->service->seedDefaults();
        $this->assertSame(0, $second['created']);
        $this->assertSame($first['total'], $second['total']);
    }

    public function test_create_rejects_duplicate_code_per_kind(): void
    {
        $this->service->create([
            'kind' => 'territory',
            'code' => 'ncr',
            'label' => 'National Capital Region',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->create([
            'kind' => 'territory',
            'code' => 'NCR',
            'label' => 'Duplicate',
        ]);
    }

    public function test_active_only_filter(): void
    {
        $active = $this->service->create([
            'kind' => 'region',
            'code' => '13',
            'label' => 'NCR',
            'is_active' => true,
        ]);
        $inactive = $this->service->create([
            'kind' => 'region',
            'code' => '14',
            'label' => 'CAR',
            'is_active' => false,
        ]);

        $list = $this->service->list('region', true);
        $ids = array_column($list, 'id');

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_delete_removes_row(): void
    {
        $row = $this->service->create([
            'kind' => 'territory',
            'code' => 'VIS',
            'label' => 'Visayas Territory',
        ]);

        $this->service->delete($row);

        $this->assertNull(RolloutGeographyLookup::query()->find($row->id));
    }

    public function test_delete_blocked_when_territory_used_on_rollout(): void
    {
        Schema::connection('tenant')->create('rollout_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('rollout_ref')->nullable();
            $table->string('mno')->nullable();
            $table->string('project_type')->nullable();
            $table->string('status')->nullable();
            $table->string('region')->nullable();
            $table->string('territory')->nullable();
            $table->timestamps();
        });

        $row = $this->service->create([
            'kind' => 'territory',
            'code' => 'NCR',
            'label' => 'National Capital Region',
        ]);

        \App\Modules\Rollout\Models\RolloutProgram::query()->create([
            'rollout_ref' => 'RP-USE',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'territory' => 'NCR',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->delete($row);
    }
}
