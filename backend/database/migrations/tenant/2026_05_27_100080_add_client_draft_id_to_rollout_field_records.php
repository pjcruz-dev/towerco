<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_candidates', function (Blueprint $table): void {
            $table->uuid('client_draft_id')->nullable()->after('rollout_program_id');
            $table->unique(['rollout_program_id', 'client_draft_id'], 'site_candidates_rollout_client_draft_unique');
        });

        Schema::table('site_hunting_daily_logs', function (Blueprint $table): void {
            $table->uuid('client_draft_id')->nullable()->after('rollout_program_id');
            $table->unique(['rollout_program_id', 'client_draft_id'], 'hunting_logs_rollout_client_draft_unique');
        });

        Schema::table('cme_daily_reports', function (Blueprint $table): void {
            $table->uuid('client_draft_id')->nullable()->after('rollout_program_id');
            $table->unique(['rollout_program_id', 'client_draft_id'], 'cme_reports_rollout_client_draft_unique');
        });
    }

    public function down(): void
    {
        Schema::table('site_candidates', function (Blueprint $table): void {
            $table->dropUnique('site_candidates_rollout_client_draft_unique');
            $table->dropColumn('client_draft_id');
        });

        Schema::table('site_hunting_daily_logs', function (Blueprint $table): void {
            $table->dropUnique('hunting_logs_rollout_client_draft_unique');
            $table->dropColumn('client_draft_id');
        });

        Schema::table('cme_daily_reports', function (Blueprint $table): void {
            $table->dropUnique('cme_reports_rollout_client_draft_unique');
            $table->dropColumn('client_draft_id');
        });
    }
};
