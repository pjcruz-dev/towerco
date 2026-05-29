<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Services\RolloutMilestoneCyclePresenter;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RolloutMilestoneCycleTest extends TestCase
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

        Schema::connection('tenant')->create('tenant_rollout_playbook_config', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('assigned_version', 32);
            $table->string('latest_platform_version', 32)->nullable();
            $table->json('playbook_snapshot');
            $table->json('day_overrides')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('rollout_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_rollout_id')->nullable();
            $table->string('playbook_version', 32)->nullable();
            $table->string('rollout_ref')->nullable();
            $table->uuid('site_id')->nullable();
            $table->string('region', 64)->nullable();
            $table->string('mno', 32)->nullable();
            $table->string('project_type', 32)->nullable();
            $table->string('status', 32);
            $table->date('endorsement_date')->nullable();
            $table->date('tssr_approved_date')->nullable();
            $table->unsignedSmallInteger('sla_working_days')->default(115);
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
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('target_start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('gate_status')->nullable();
            $table->string('gate_label')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('site_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->unsignedTinyInteger('candidate_number')->default(1);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('site_hunting_daily_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->date('log_date')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('cme_daily_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->date('report_date')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('site_profitability_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->timestamps();
        });
    }

    public function test_bts_rollout_returns_nineteen_milestone_rows_with_v2_site_hunting_window(): void
    {
        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        $program = RolloutProgram::query()->create([
            'status' => 'tssr_mno_approval',
            'project_type' => 'bts',
            'mno' => 'globe',
            'rollout_ref' => 'RP-MILESTONE-1',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 115,
        ]);

        $rows = app(RolloutMilestoneCyclePresenter::class)->forProgram($program);

        $this->assertCount(19, $rows);

        $siteHunting = collect($rows)->firstWhere('phase_key', 'site_hunting');
        $preAssessment = collect($rows)->firstWhere('phase_key', 'pre_assessment');
        $this->assertNotNull($siteHunting);
        $this->assertNotNull($preAssessment);
        $this->assertSame(7, (int) $siteHunting['target_working_days'] + (int) $preAssessment['target_working_days']);
        $this->assertSame('2026-04-09', $siteHunting['target_date']);

        $moc = collect($rows)->firstWhere('phase_key', 'moc_securing');
        $this->assertNotNull($moc);
        $this->assertSame('day_one', $moc['anchor']);
        $this->assertSame('2026-05-04', $moc['target_date']);
    }

    public function test_batch_rollout_returns_empty_milestone_cycles(): void
    {
        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        $program = RolloutProgram::query()->create([
            'status' => 'batch',
            'project_type' => 'bts',
            'mno' => 'globe',
            'rollout_ref' => 'RP-BATCH-1',
            'sla_working_days' => 115,
        ]);

        $rows = app(RolloutMilestoneCyclePresenter::class)->forProgram($program);

        $this->assertSame([], $rows);
    }

    public function test_detail_presenter_embeds_milestone_cycles_and_summary(): void
    {
        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        Carbon::setTestNow('2026-04-15');

        $program = RolloutProgram::query()->create([
            'status' => 'site_hunting',
            'project_type' => 'bts',
            'mno' => 'globe',
            'rollout_ref' => 'RP-MILESTONE-2',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 115,
        ]);

        $detail = app(RolloutProgramPresenter::class)->detail($program->fresh());

        $this->assertArrayHasKey('milestone_cycles', $detail);
        $this->assertArrayHasKey('milestone_cycles_summary', $detail);
        $this->assertCount(19, $detail['milestone_cycles']);
        $this->assertSame(19, $detail['milestone_cycles_summary']['total']);
        $this->assertGreaterThan(0, $detail['milestone_cycles_summary']['overdue']);

        Carbon::setTestNow();
    }

    public function test_rtb_rollout_returns_nineteen_milestone_rows_with_post_moc_budget_of_eighty_five(): void
    {
        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        $program = RolloutProgram::query()->create([
            'status' => 'permitting',
            'project_type' => 'rtb',
            'mno' => 'globe',
            'rollout_ref' => 'RP-RTB-MILESTONE',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 85,
        ]);

        $rows = app(RolloutMilestoneCyclePresenter::class)->forProgram($program);

        $this->assertCount(19, $rows);

        $postMoc = false;
        $postSum = 0;
        foreach ($rows as $row) {
            if ($row['phase_key'] === 'moc_securing') {
                $postMoc = true;
            }
            if ($postMoc) {
                $postSum += (int) $row['target_working_days'];
            }
        }

        $this->assertSame(85, $postSum);
    }

    public function test_colocation_rollout_returns_three_milestone_rows(): void
    {
        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        $program = RolloutProgram::query()->create([
            'status' => 'permitting',
            'project_type' => 'colocation',
            'mno' => 'globe',
            'rollout_ref' => 'RP-COLO-MILESTONE',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 30,
        ]);

        $rows = app(RolloutMilestoneCyclePresenter::class)->forProgram($program);

        $this->assertCount(3, $rows);
        $this->assertSame('site_license', $rows[0]['phase_key']);
        $this->assertSame('day_one', $rows[0]['anchor']);
        $this->assertSame(30, array_sum(array_column($rows, 'target_working_days')));
    }
}
