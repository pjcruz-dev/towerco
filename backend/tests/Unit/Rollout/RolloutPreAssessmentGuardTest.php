<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Support\RolloutPreAssessmentGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutPreAssessmentGuardTest extends TestCase
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

        Schema::connection('tenant')->create('site_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->unsignedSmallInteger('candidate_number')->default(1);
            $table->string('status')->default('scouted');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function test_pre_assessment_blocked_until_site_hunting_passed(): void
    {
        $program = $this->seedProgram(huntingGate: 'pending', selected: true);

        $this->assertFalse(RolloutPreAssessmentGuard::isReadyForPreAssessment($program->fresh(['timelinePhases', 'candidates'])));
        $this->expectException(ValidationException::class);
        RolloutPreAssessmentGuard::assertReadyForPreAssessment($program->fresh(['timelinePhases', 'candidates']));
    }

    public function test_pre_assessment_ready_after_hunting_passed_and_selection(): void
    {
        $program = $this->seedProgram(huntingGate: 'passed', selected: true);

        $this->assertTrue(RolloutPreAssessmentGuard::isReadyForPreAssessment($program->fresh(['timelinePhases', 'candidates'])));
    }

    public function test_tssr_blocked_until_pre_assessment_passed(): void
    {
        $program = $this->seedProgram(huntingGate: 'passed', selected: true, preAssessmentGate: 'pending');

        $this->expectException(ValidationException::class);
        RolloutPreAssessmentGuard::assertReadyForTssr($program->fresh(['timelinePhases', 'candidates']));
    }

    public function test_tssr_allowed_after_pre_assessment_passed(): void
    {
        $program = $this->seedProgram(huntingGate: 'passed', selected: true, preAssessmentGate: 'passed');

        RolloutPreAssessmentGuard::assertReadyForTssr($program->fresh(['timelinePhases', 'candidates']));
        $this->assertTrue(true);
    }

    private function seedProgram(
        string $huntingGate,
        bool $selected,
        string $preAssessmentGate = 'pending',
    ): RolloutProgram {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P3',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'site_hunting',
            'label' => 'Site Hunting',
            'anchor' => 'endorsement',
            'gate_status' => $huntingGate,
            'sort_order' => 1,
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'pre_assessment',
            'label' => 'Pre-assessment',
            'anchor' => 'endorsement',
            'gate_status' => $preAssessmentGate,
            'sort_order' => 2,
        ]);

        foreach ([1, 2, 3] as $n) {
            SiteCandidate::query()->create([
                'rollout_program_id' => $program->id,
                'candidate_number' => $n,
                'status' => $selected && $n === 1 ? 'selected' : 'scouted',
                'label' => "C{$n}",
            ]);
        }

        return $program;
    }
}
