<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Support\RolloutSaqSelectGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutSaqSelectGuardTest extends TestCase
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

        Schema::connection('tenant')->create('site_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->unsignedSmallInteger('candidate_number')->default(1);
            $table->string('status')->default('scouted');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function test_select_blocked_until_three_active_candidates(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P2-A',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
        ]);

        SiteCandidate::query()->create([
            'rollout_program_id' => $program->id,
            'candidate_number' => 1,
            'status' => 'scouted',
            'label' => 'A',
        ]);
        SiteCandidate::query()->create([
            'rollout_program_id' => $program->id,
            'candidate_number' => 2,
            'status' => 'scouted',
            'label' => 'B',
        ]);

        $this->assertFalse(RolloutSaqSelectGuard::isReadyToSelect($program->fresh('candidates')));
        $this->expectException(ValidationException::class);
        RolloutSaqSelectGuard::assertReadyToSelect($program->fresh('candidates'));
    }

    public function test_gate_ready_requires_selection(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P2-B',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
        ]);

        foreach ([1, 2, 3] as $n) {
            SiteCandidate::query()->create([
                'rollout_program_id' => $program->id,
                'candidate_number' => $n,
                'status' => 'scouted',
                'label' => "C{$n}",
            ]);
        }

        $fresh = $program->fresh('candidates');
        $this->assertTrue(RolloutSaqSelectGuard::isReadyToSelect($fresh));
        $this->assertFalse(RolloutSaqSelectGuard::isSiteHuntingGateReady($fresh));

        SiteCandidate::query()
            ->where('rollout_program_id', $program->id)
            ->where('candidate_number', 1)
            ->update(['status' => 'selected']);

        $this->assertTrue(RolloutSaqSelectGuard::isSiteHuntingGateReady($program->fresh('candidates')));
    }

    public function test_rejected_candidates_do_not_count_toward_minimum(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P2-C',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
        ]);

        SiteCandidate::query()->create([
            'rollout_program_id' => $program->id,
            'candidate_number' => 1,
            'status' => 'scouted',
            'label' => 'A',
        ]);
        SiteCandidate::query()->create([
            'rollout_program_id' => $program->id,
            'candidate_number' => 2,
            'status' => 'scouted',
            'label' => 'B',
        ]);
        SiteCandidate::query()->create([
            'rollout_program_id' => $program->id,
            'candidate_number' => 3,
            'status' => 'rejected',
            'label' => 'C',
        ]);

        $this->assertSame(2, RolloutSaqSelectGuard::activeCandidateCount($program->fresh('candidates')));
        $this->assertFalse(RolloutSaqSelectGuard::isReadyToSelect($program->fresh('candidates')));
    }
}
