<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Support\RolloutConstructionRfiGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutConstructionRfiGuardTest extends TestCase
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
            $table->string('rollout_ref')->nullable();
            $table->string('mno')->nullable();
            $table->string('project_type')->nullable();
            $table->string('status')->nullable();
            $table->unsignedSmallInteger('sla_working_days')->nullable();
            $table->string('playbook_version')->nullable();
            $table->date('endorsement_date')->nullable();
            $table->date('tssr_approved_date')->nullable();
            $table->date('actual_rfi_date')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('rollout_timeline_phases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->string('phase_key');
            $table->string('label')->nullable();
            $table->string('anchor')->nullable();
            $table->unsignedSmallInteger('working_day_start')->default(0);
            $table->unsignedSmallInteger('working_day_end')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('gate_status')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_rfi_blocked_until_p6_complete(): void
    {
        $program = $this->seedProgram(skomGate: 'pending', rfi: null);

        $this->assertFalse(RolloutConstructionRfiGuard::isReadyForRfi($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutConstructionRfiGuard::assertReadyForRfi($program->fresh(['timelinePhases']));
    }

    public function test_rfi_ready_after_skom_passed(): void
    {
        $program = $this->seedProgram(skomGate: 'passed', rfi: null);

        RolloutConstructionRfiGuard::assertReadyForRfi($program->fresh(['timelinePhases']));
        $this->assertTrue(RolloutConstructionRfiGuard::isReadyForConstruction($program->fresh(['timelinePhases'])));
    }

    public function test_close_out_blocked_until_rfi(): void
    {
        $program = $this->seedProgram(skomGate: 'passed', rfi: null);

        $this->expectException(ValidationException::class);
        RolloutConstructionRfiGuard::assertPassedBeforeCloseOut($program->fresh(['timelinePhases']));
    }

    public function test_close_out_ready_after_rfi(): void
    {
        $program = $this->seedProgram(skomGate: 'passed', rfi: '2026-08-01');

        RolloutConstructionRfiGuard::assertPassedBeforeCloseOut($program->fresh(['timelinePhases']));
        $this->assertTrue(RolloutConstructionRfiGuard::isRfiRecorded($program));
    }

    private function seedProgram(string $skomGate, ?string $rfi): RolloutProgram
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P7',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => $rfi ? 'completed' : 'permitting',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-05-01',
            'actual_rfi_date' => $rfi,
        ]);

        foreach (
            [
                ['pre_construction', 'passed', 1],
                ['permitting', 'passed', 2],
                ['skom', $skomGate, 3],
                ['construction', 'pending', 4],
                ['site_license', 'pending', 5],
            ] as [$key, $gate, $sort]
        ) {
            RolloutTimelinePhase::query()->create([
                'rollout_program_id' => $program->id,
                'phase_key' => $key,
                'label' => $key,
                'anchor' => 'tssr_approved',
                'gate_status' => $gate,
                'sort_order' => $sort,
            ]);
        }

        return $program;
    }
}
