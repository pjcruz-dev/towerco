<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollout_programs', function (Blueprint $table): void {
            $table->string('mno_anchor_site_id', 128)->nullable()->after('tco_site_id');
            $table->string('alliance_tag', 128)->nullable()->after('territory');
            $table->string('area', 64)->nullable()->after('alliance_tag');
            $table->text('site_license_remarks')->nullable()->after('site_license_executed_date');
            $table->date('energization_tempo_date')->nullable()->after('site_license_remarks');
            $table->date('rfti_signed_tempo_date')->nullable()->after('energization_tempo_date');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->text('full_address')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('rollout_programs', function (Blueprint $table): void {
            $table->dropColumn([
                'mno_anchor_site_id',
                'alliance_tag',
                'area',
                'site_license_remarks',
                'energization_tempo_date',
                'rfti_signed_tempo_date',
            ]);
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('full_address');
        });
    }
};
