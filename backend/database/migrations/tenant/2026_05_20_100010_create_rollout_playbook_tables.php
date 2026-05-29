<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_rollout_playbook_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('assigned_version', 32);
            $table->string('latest_platform_version', 32)->nullable();
            $table->json('playbook_snapshot');
            $table->json('day_overrides')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();
        });

        Schema::create('rollout_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_rollout_id')->nullable();
            $table->string('playbook_version', 32);
            $table->string('rollout_ref')->unique();
            $table->string('tco_site_id')->nullable()->unique();
            $table->foreignUuid('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('mno', 32);
            $table->string('project_type', 32);
            $table->string('endorsement_ref')->nullable();
            $table->date('endorsement_date')->nullable();
            $table->string('search_ring_name')->nullable();
            $table->string('region', 64)->nullable();
            $table->string('territory', 64)->nullable();
            $table->string('status', 32)->default('draft');
            $table->date('tssr_approved_date')->nullable();
            $table->unsignedSmallInteger('sla_working_days');
            $table->date('target_rfi_working_date')->nullable();
            $table->date('actual_rfi_date')->nullable();
            $table->integer('sla_variance_working_days')->nullable();
            $table->foreignUuid('saq_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cme_pm_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('pmo_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('parent_rollout_id')->references('id')->on('rollout_programs')->nullOnDelete();
        });

        Schema::create('rollout_timeline_phases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_program_id')->constrained('rollout_programs')->cascadeOnDelete();
            $table->string('phase_key', 64);
            $table->string('label');
            $table->string('owner_role', 64)->nullable();
            $table->string('anchor', 32)->default('endorsement');
            $table->unsignedSmallInteger('working_day_start');
            $table->unsignedSmallInteger('working_day_end');
            $table->unsignedSmallInteger('target_working_day_end')->nullable();
            $table->date('target_start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('gate_status', 32)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['rollout_program_id', 'phase_key']);
        });

        Schema::create('site_candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_program_id')->constrained('rollout_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('candidate_number');
            $table->string('status', 32)->default('scouted');
            $table->string('label')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('lessor_name')->nullable();
            $table->string('lessor_contact')->nullable();
            $table->decimal('proposed_lease_rate_php', 14, 2)->nullable();
            $table->text('row_notes')->nullable();
            $table->text('power_notes')->nullable();
            $table->text('hazard_notes')->nullable();
            $table->json('photo_links')->nullable();
            $table->json('lease_package')->nullable();
            $table->string('rejection_reason_code', 64)->nullable();
            $table->text('rejection_notes')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignUuid('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['rollout_program_id', 'candidate_number']);
        });

        Schema::create('site_hunting_daily_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_program_id')->constrained('rollout_programs')->cascadeOnDelete();
            $table->date('log_date');
            $table->foreignUuid('hunter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->json('candidate_ids')->nullable();
            $table->unsignedSmallInteger('candidates_identified_count')->default(0);
            $table->json('photo_links')->nullable();
            $table->timestamps();

            $table->unique(['rollout_program_id', 'log_date']);
        });

        Schema::create('cme_daily_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_program_id')->constrained('rollout_programs')->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedSmallInteger('day_number')->nullable();
            $table->unsignedSmallInteger('construction_working_days_total')->default(44);
            $table->foreignUuid('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('weather_am', 32)->nullable();
            $table->string('weather_pm', 32)->nullable();
            $table->unsignedInteger('workforce_count')->nullable();
            $table->unsignedInteger('manhours_today')->nullable();
            $table->unsignedInteger('manhours_cumulative')->nullable();
            $table->decimal('physical_progress_pct', 5, 2)->nullable();
            $table->decimal('physical_progress_plan_pct', 5, 2)->nullable();
            $table->text('activities_completed')->nullable();
            $table->text('activities_planned_tomorrow')->nullable();
            $table->text('quality_issues')->nullable();
            $table->string('safety_incidents', 64)->nullable();
            $table->boolean('toolbox_meeting_held')->default(false);
            $table->text('lessor_neighbor_issues')->nullable();
            $table->text('risks_flagged')->nullable();
            $table->json('photo_links')->nullable();
            $table->timestamps();

            $table->unique(['rollout_program_id', 'report_date']);
        });

        Schema::create('site_profitability_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_program_id')->unique()->constrained('rollout_programs')->cascadeOnDelete();
            $table->json('baseline');
            $table->json('actual');
            $table->decimal('vo_cost_cumulative', 14, 2)->default(0);
            $table->decimal('ld_accrued_php', 14, 2)->default(0);
            $table->string('variance_category', 64)->nullable();
            $table->string('profitability_status', 32)->default('on_track');
            $table->decimal('anchor_tenant_lease_fee_php', 14, 2)->nullable();
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_profitability_records');
        Schema::dropIfExists('cme_daily_reports');
        Schema::dropIfExists('site_hunting_daily_logs');
        Schema::dropIfExists('site_candidates');
        Schema::dropIfExists('rollout_timeline_phases');
        Schema::dropIfExists('rollout_programs');
        Schema::dropIfExists('tenant_rollout_playbook_config');
    }
};
