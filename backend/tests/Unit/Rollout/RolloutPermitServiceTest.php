<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutPermit;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutPermitService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RolloutPermitServiceTest extends TestCase
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

        Schema::connection('tenant')->create('rollout_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('rollout_permits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->string('permit_type', 64);
            $table->date('applied_date')->nullable();
            $table->date('secured_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_sync_creates_and_updates_permit_rows(): void
    {
        $program = RolloutProgram::query()->create([
            'status' => 'permitting',
        ]);

        $service = app(RolloutPermitService::class);

        $rows = $service->syncForProgram($program, [
            [
                'permit_type' => 'building_permit',
                'applied_date' => '2026-05-01',
                'secured_date' => '2026-05-20',
            ],
        ]);

        $building = collect($rows)->firstWhere('permit_type', 'building_permit');
        $this->assertNotNull($building);
        $this->assertSame('2026-05-01', $building['applied_date']);
        $this->assertSame('2026-05-20', $building['secured_date']);
        $this->assertSame('permitting', $building['timeline_phase_key']);
        $this->assertSame(7, count($rows));
        $this->assertSame(1, RolloutPermit::query()->count());
    }
}
