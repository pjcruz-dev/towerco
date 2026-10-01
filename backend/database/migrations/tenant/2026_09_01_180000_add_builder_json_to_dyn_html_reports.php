<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dyn_html_reports')) {
            return;
        }
        if (Schema::hasColumn('dyn_html_reports', 'builder_json')) {
            return;
        }

        Schema::table('dyn_html_reports', function (Blueprint $table): void {
            $table->json('builder_json')->nullable()->after('js_source');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dyn_html_reports') || ! Schema::hasColumn('dyn_html_reports', 'builder_json')) {
            return;
        }

        Schema::table('dyn_html_reports', function (Blueprint $table): void {
            $table->dropColumn('builder_json');
        });
    }
};
