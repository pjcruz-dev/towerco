<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Services\RolloutProgramService;
use App\Modules\Rollout\Services\RolloutSlaRecalculationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RolloutSlaRecalculationServiceTest extends TestCase
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

        Schema::connection('tenant')->create('rollout_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('rollout_ref')->nullable();
            $table->string('region', 64)->nullable();
            $table->string('mno', 32)->nullable();
            $table->string('project_type', 32)->nullable();
            $table->string('status', 32);
            $table->date('endorsement_date')->nullable();
            $table->date('tssr_approved_date')->nullable();
            $table->unsignedSmallInteger('sla_working_days')->default(120);
            $table->date('target_rfi_working_date')->nullable();
            $table->date('actual_rfi_date')->nullable();
            $table->integer('sla_variance_working_days')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('rollout_timeline_phases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->string('phase_key');
            $table->string('label');
            $table->string('owner_role')->nullable();
            $table->string('anchor', 32);
            $table->unsignedSmallInteger('working_day_start');
            $table->unsignedSmallInteger('working_day_end');
            $table->date('target_start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_recalculate_shifts_target_rfi_when_holiday_is_added(): void
    {
        $program = RolloutProgram::query()->create([
            'status' => 'permitting',
            'region' => 'ncr',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 5,
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'phase_1',
            'label' => 'Phase 1',
            'anchor' => 'tssr_approved',
            'working_day_start' => 1,
            'working_day_end' => 5,
        ]);

        $service = app(RolloutSlaRecalculationService::class);
        $before = $service->recalculateProgram($program->fresh(['timelinePhases']));

        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-05-01',
            'name' => 'Labor Day',
            'region' => null,
            'calendar_year' => 2026,
        ]);

        $after = $service->recalculateProgram($program->fresh(['timelinePhases']));

        $this->assertSame('2026-05-05', $before->target_rfi_working_date?->toDateString());
        $this->assertSame('2026-05-06', $after->target_rfi_working_date?->toDateString());
    }

    public function test_regional_holiday_only_shifts_matching_rollout_region(): void
    {
        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-05-01',
            'name' => 'NCR local holiday',
            'region' => 'ncr',
            'calendar_year' => 2026,
        ]);

        $ncrProgram = RolloutProgram::query()->create([
            'status' => 'permitting',
            'region' => 'ncr',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 5,
        ]);

        $visayasProgram = RolloutProgram::query()->create([
            'status' => 'permitting',
            'region' => 'visayas',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 5,
        ]);

        $service = app(RolloutSlaRecalculationService::class);
        $ncr = $service->recalculateProgram($ncrProgram);
        $visayas = $service->recalculateProgram($visayasProgram);

        $this->assertSame('2026-05-06', $ncr->target_rfi_working_date?->toDateString());
        $this->assertSame('2026-05-05', $visayas->target_rfi_working_date?->toDateString());
    }

    public function test_record_rfi_computes_positive_variance_when_late(): void
    {
        $program = RolloutProgram::query()->create([
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 5,
            'mno' => 'glo',
            'project_type' => 'bts',
            'rollout_ref' => 'RP-TEST',
        ]);

        $service = app(RolloutProgramService::class);
        $updated = $service->recordRfi($program, Carbon::parse('2026-05-15'));

        $this->assertSame('completed', $updated->status);
        $this->assertSame('2026-05-15', $updated->actual_rfi_date?->toDateString());
        $this->assertGreaterThan(0, $updated->sla_variance_working_days);
    }
}
