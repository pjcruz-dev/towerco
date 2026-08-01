<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Support\RolloutTssrDayOneGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutTssrDayOneGuardTest extends TestCase
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

        Schema::connection('tenant')->create('site_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->unsignedSmallInteger('candidate_number')->default(1);
            $table->string('status')->default('scouted');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function test_day_one_blocked_until_tssr_creation_passed(): void
    {
        $program = $this->seedProgram(tssrCreationGate: 'pending');

        $this->expectException(ValidationException::class);
        RolloutTssrDayOneGuard::assertReadyForDayOne($program->fresh(['timelinePhases', 'candidates']));
    }

    public function test_day_one_ready_after_tssr_creation_passed(): void
    {
        $program = $this->seedProgram(tssrCreationGate: 'passed');

        RolloutTssrDayOneGuard::assertReadyForDayOne($program->fresh(['timelinePhases', 'candidates']));
        $this->assertTrue(true);
    }

    public function test_tssr_mno_gate_blocked_until_creation_passed(): void
    {
        $program = $this->seedProgram(tssrCreationGate: 'pending');

        $this->expectException(ValidationException::class);
        RolloutTssrDayOneGuard::assertReadyForTssrMnoGate($program->fresh(['timelinePhases', 'candidates']));
    }

    private function seedProgram(string $tssrCreationGate): RolloutProgram
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P5',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
        ]);

        foreach (
            [
                ['site_hunting', 'passed', 1],
                ['pre_assessment', 'passed', 2],
                ['moc_col', 'passed', 3],
                ['tssr_creation', $tssrCreationGate, 4],
                ['tssr_mno_approval', 'pending', 5],
            ] as [$key, $gate, $sort]
        ) {
            RolloutTimelinePhase::query()->create([
                'rollout_program_id' => $program->id,
                'phase_key' => $key,
                'label' => $key,
                'anchor' => 'endorsement',
                'gate_status' => $gate,
                'sort_order' => $sort,
            ]);
        }

        foreach ([1, 2, 3] as $n) {
            SiteCandidate::query()->create([
                'rollout_program_id' => $program->id,
                'candidate_number' => $n,
                'status' => $n === 1 ? 'selected' : 'scouted',
                'label' => "C{$n}",
            ]);
        }

        return $program;
    }
}
