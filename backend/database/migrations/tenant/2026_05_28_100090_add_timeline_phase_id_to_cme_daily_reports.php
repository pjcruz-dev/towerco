<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cme_daily_reports', function (Blueprint $table): void {
            $table->foreignUuid('timeline_phase_id')
                ->nullable()
                ->after('rollout_program_id')
                ->constrained('rollout_timeline_phases')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cme_daily_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('timeline_phase_id');
        });
    }
};
