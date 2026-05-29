<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollout_programs', function (Blueprint $table) {
            $table->date('doa_execution_date')->nullable()->after('tssr_approved_date');
            $table->date('site_license_executed_date')->nullable()->after('doa_execution_date');
        });

        Schema::table('rollout_timeline_phases', function (Blueprint $table) {
            $table->string('gate_label')->nullable()->after('gate_status');
        });
    }

    public function down(): void
    {
        Schema::table('rollout_timeline_phases', function (Blueprint $table) {
            $table->dropColumn('gate_label');
        });

        Schema::table('rollout_programs', function (Blueprint $table) {
            $table->dropColumn(['doa_execution_date', 'site_license_executed_date']);
        });
    }
};
