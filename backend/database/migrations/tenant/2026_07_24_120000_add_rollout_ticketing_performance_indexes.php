<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rollout_programs')) {
            Schema::table('rollout_programs', static function (Blueprint $table): void {
                // Matches RolloutProgramIndexService list filters (parent scope + status + sort).
                $table->index(['parent_rollout_id', 'status', 'updated_at'], 'rollout_programs_parent_status_updated_idx');
                $table->index('status', 'rollout_programs_status_idx');
                $table->index('mno', 'rollout_programs_mno_idx');
                $table->index('project_type', 'rollout_programs_project_type_idx');
                $table->index('region', 'rollout_programs_region_idx');
            });
        }

        if (Schema::hasTable('rollout_timeline_phases')) {
            Schema::table('rollout_timeline_phases', static function (Blueprint $table): void {
                // Dashboard pending-gates count filters on gate_status.
                $table->index('gate_status', 'rollout_timeline_phases_gate_status_idx');
            });
        }

        if (Schema::hasTable('ticketing_tickets')) {
            Schema::table('ticketing_tickets', static function (Blueprint $table): void {
                // Dashboard KPI + category analytics filters. (status, sla_due_at) already exists.
                $table->index(['priority', 'status'], 'ticketing_tickets_priority_status_idx');
                $table->index(['category', 'status'], 'ticketing_tickets_category_status_idx');
                $table->index(['status', 'resolved_at'], 'ticketing_tickets_status_resolved_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rollout_programs')) {
            Schema::table('rollout_programs', static function (Blueprint $table): void {
                $table->dropIndex('rollout_programs_parent_status_updated_idx');
                $table->dropIndex('rollout_programs_status_idx');
                $table->dropIndex('rollout_programs_mno_idx');
                $table->dropIndex('rollout_programs_project_type_idx');
                $table->dropIndex('rollout_programs_region_idx');
            });
        }

        if (Schema::hasTable('rollout_timeline_phases')) {
            Schema::table('rollout_timeline_phases', static function (Blueprint $table): void {
                $table->dropIndex('rollout_timeline_phases_gate_status_idx');
            });
        }

        if (Schema::hasTable('ticketing_tickets')) {
            Schema::table('ticketing_tickets', static function (Blueprint $table): void {
                $table->dropIndex('ticketing_tickets_priority_status_idx');
                $table->dropIndex('ticketing_tickets_category_status_idx');
                $table->dropIndex('ticketing_tickets_status_resolved_idx');
            });
        }
    }
};
