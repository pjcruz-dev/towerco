<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Support\RolloutCloseOutGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutCloseOutGuardTest extends TestCase
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
            $table->date('site_license_executed_date')->nullable();
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

    public function test_site_license_blocked_until_rfi(): void
    {
        $program = $this->seedProgram(rfi: null, siteLicenseGate: 'pending', handoverGate: 'pending');

        $this->assertFalse(RolloutCloseOutGuard::isReadyForSiteLicense($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutCloseOutGuard::assertReadyForSiteLicense($program->fresh(['timelinePhases']));
    }

    public function test_handover_blocked_until_site_license_passed(): void
    {
        $program = $this->seedProgram(rfi: '2026-08-01', siteLicenseGate: 'pending', handoverGate: 'pending');

        $this->assertTrue(RolloutCloseOutGuard::isReadyForSiteLicense($program->fresh(['timelinePhases'])));
        $this->assertFalse(RolloutCloseOutGuard::isReadyForHandover($program->fresh(['timelinePhases'])));
        $this->expectException(ValidationException::class);
        RolloutCloseOutGuard::assertReadyForHandover($program->fresh(['timelinePhases']));
    }

    public function test_handover_ready_after_site_license_gate(): void
    {
        $program = $this->seedProgram(rfi: '2026-08-01', siteLicenseGate: 'passed', handoverGate: 'pending');

        RolloutCloseOutGuard::assertReadyForHandover($program->fresh(['timelinePhases']));
        $this->assertTrue(true);
    }

    public function test_site_license_passed_via_executed_date(): void
    {
        $program = $this->seedProgram(
            rfi: '2026-08-01',
            siteLicenseGate: 'pending',
            handoverGate: 'pending',
            siteLicenseDate: '2026-08-10',
        );

        $this->assertTrue(RolloutCloseOutGuard::isSiteLicensePassed($program->fresh(['timelinePhases'])));
        RolloutCloseOutGuard::assertReadyForHandover($program->fresh(['timelinePhases']));
    }

    public function test_close_out_complete_after_handover(): void
    {
        $program = $this->seedProgram(rfi: '2026-08-01', siteLicenseGate: 'passed', handoverGate: 'passed');

        $this->assertTrue(RolloutCloseOutGuard::isCloseOutComplete($program->fresh(['timelinePhases'])));
    }

    private function seedProgram(
        ?string $rfi,
        string $siteLicenseGate,
        string $handoverGate,
        ?string $siteLicenseDate = null,
    ): RolloutProgram {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-P8',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => $rfi ? 'completed' : 'permitting',
            'sla_working_days' => 115,
            'playbook_version' => '3.0.0',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-05-01',
            'actual_rfi_date' => $rfi,
            'site_license_executed_date' => $siteLicenseDate,
        ]);

        foreach (
            [
                ['pre_construction', 'passed', 1],
                ['permitting', 'passed', 2],
                ['skom', 'passed', 3],
                ['construction', 'passed', 4],
                ['site_license', $siteLicenseGate, 5],
                ['handover_operations', $handoverGate, 6],
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
