<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rollout_programs')) {
            return;
        }

        Schema::table('rollout_programs', static function (Blueprint $table): void {
            if (! Schema::hasColumn('rollout_programs', 'sla_remaining_working_days')) {
                // Denormalized working days from the reference date to target RFI, recomputed
                // daily so SLA-at-risk dashboards filter in SQL instead of a PHP calendar loop.
                $table->integer('sla_remaining_working_days')->nullable()->after('sla_variance_working_days');
            }
            if (! Schema::hasColumn('rollout_programs', 'sla_risk_computed_on')) {
                $table->date('sla_risk_computed_on')->nullable()->after('sla_remaining_working_days');
            }
        });

        if (Schema::hasColumn('rollout_programs', 'sla_remaining_working_days')) {
            Schema::table('rollout_programs', static function (Blueprint $table): void {
                $table->index(['status', 'sla_remaining_working_days'], 'rollout_programs_status_sla_remaining_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rollout_programs')) {
            return;
        }

        if (Schema::hasColumn('rollout_programs', 'sla_remaining_working_days')) {
            Schema::table('rollout_programs', static function (Blueprint $table): void {
                $table->dropIndex('rollout_programs_status_sla_remaining_idx');
            });
        }

        Schema::table('rollout_programs', static function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('rollout_programs', 'sla_remaining_working_days') ? 'sla_remaining_working_days' : null,
                Schema::hasColumn('rollout_programs', 'sla_risk_computed_on') ? 'sla_risk_computed_on' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
