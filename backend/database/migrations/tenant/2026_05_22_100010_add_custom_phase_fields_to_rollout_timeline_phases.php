<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('rollout_timeline_phases')) {
            return;
        }

        Schema::connection('tenant')->table('rollout_timeline_phases', function (Blueprint $table): void {
            if (! Schema::connection('tenant')->hasColumn('rollout_timeline_phases', 'counts_toward_sla')) {
                $table->boolean('counts_toward_sla')->default(true)->after('gate_label');
            }
            if (! Schema::connection('tenant')->hasColumn('rollout_timeline_phases', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('counts_toward_sla');
            }
            if (! Schema::connection('tenant')->hasColumn('rollout_timeline_phases', 'catalog_phase_id')) {
                $table->uuid('catalog_phase_id')->nullable()->after('is_custom');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('rollout_timeline_phases')) {
            return;
        }

        Schema::connection('tenant')->table('rollout_timeline_phases', function (Blueprint $table): void {
            if (Schema::connection('tenant')->hasColumn('rollout_timeline_phases', 'catalog_phase_id')) {
                $table->dropColumn('catalog_phase_id');
            }
            if (Schema::connection('tenant')->hasColumn('rollout_timeline_phases', 'is_custom')) {
                $table->dropColumn('is_custom');
            }
            if (Schema::connection('tenant')->hasColumn('rollout_timeline_phases', 'counts_toward_sla')) {
                $table->dropColumn('counts_toward_sla');
            }
        });
    }
};
