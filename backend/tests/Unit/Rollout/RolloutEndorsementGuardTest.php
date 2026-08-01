<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Support\RolloutEndorsementGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutEndorsementGuardTest extends TestCase
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
            $table->string('endorsement_ref')->nullable();
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

    public function test_not_established_without_date_or_passed_gate(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P1-A',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'endorsement',
            'label' => 'Endorsement',
            'anchor' => 'endorsement',
            'gate_status' => 'pending',
            'sort_order' => 0,
        ]);

        $this->assertFalse(RolloutEndorsementGuard::isEstablished($program->fresh('timelinePhases')));
        $this->expectException(ValidationException::class);
        RolloutEndorsementGuard::assertEstablished($program->fresh('timelinePhases'));
    }

    public function test_established_when_endorsement_date_set(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P1-B',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
        ]);

        $this->assertTrue(RolloutEndorsementGuard::isEstablished($program));
    }

    public function test_established_when_endorsement_gate_passed(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P1-C',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $program->id,
            'phase_key' => 'endorsement',
            'label' => 'Endorsement',
            'anchor' => 'endorsement',
            'gate_status' => 'passed',
            'sort_order' => 0,
        ]);

        $this->assertTrue(RolloutEndorsementGuard::isEstablished($program->fresh('timelinePhases')));
    }
}
