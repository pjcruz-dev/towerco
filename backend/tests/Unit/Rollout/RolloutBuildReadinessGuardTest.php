<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Support\RolloutBuildReadinessGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutBuildReadinessGuardTest extends TestCase
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

    public function test_pre_construction_blocked_until_day_one(): void
    {
        $program = $this->seedProgram(dayOne: null, preCon: 'pending', permitting: 'pending', skom: 'pending');

        $this->assertFalse(RolloutBuildReadinessGuard::isReadyForPreConstruction($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutBuildReadinessGuard::assertReadyForPreConstruction($program->fresh(['timelinePhases']));
    }

    public function test_permitting_blocked_until_pre_construction_passed(): void
    {
        $program = $this->seedProgram(dayOne: '2026-05-01', preCon: 'pending', permitting: 'pending', skom: 'pending');

        $this->assertTrue(RolloutBuildReadinessGuard::isReadyForPreConstruction($program->fresh(['timelinePhases'])));
        $this->assertFalse(RolloutBuildReadinessGuard::isReadyForPermitting($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutBuildReadinessGuard::assertReadyForPermitting($program->fresh(['timelinePhases']));
    }

    public function test_skom_blocked_until_permitting_passed(): void
    {
        $program = $this->seedProgram(dayOne: '2026-05-01', preCon: 'passed', permitting: 'pending', skom: 'pending');

        $this->assertTrue(RolloutBuildReadinessGuard::isReadyForPermitting($program->fresh(['timelinePhases'])));
        $this->assertFalse(RolloutBuildReadinessGuard::isReadyForSkom($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutBuildReadinessGuard::assertReadyForSkom($program->fresh(['timelinePhases']));
    }

    public function test_construction_blocked_until_skom_passed(): void
    {
        $program = $this->seedProgram(dayOne: '2026-05-01', preCon: 'passed', permitting: 'passed', skom: 'pending');

        $this->assertTrue(RolloutBuildReadinessGuard::isReadyForSkom($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutBuildReadinessGuard::assertPassedBeforeConstruction($program->fresh(['timelinePhases']));
    }

    public function test_construction_ready_after_skom_passed(): void
    {
        $program = $this->seedProgram(dayOne: '2026-05-01', preCon: 'passed', permitting: 'passed', skom: 'passed');

        RolloutBuildReadinessGuard::assertPassedBeforeConstruction($program->fresh(['timelinePhases']));
        $this->assertTrue(RolloutBuildReadinessGuard::isBuildReadinessComplete($program->fresh(['timelinePhases'])));
    }

    private function seedProgram(
        ?string $dayOne,
        string $preCon,
        string $permitting,
        string $skom,
    ): RolloutProgram {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P6',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'permitting',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => $dayOne,
        ]);

        foreach (
            [
                ['pre_construction', $preCon, 1],
                ['permitting', $permitting, 2],
                ['skom', $skom, 3],
                ['construction', 'pending', 4],
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
