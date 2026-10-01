<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dyn_entities')) {
            return;
        }

        Schema::table('dyn_entities', function (Blueprint $table): void {
            if (! Schema::hasColumn('dyn_entities', 'print_settings_json')) {
                $table->json('print_settings_json')->nullable()->after('related_tabs_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dyn_entities')) {
            return;
        }

        Schema::table('dyn_entities', function (Blueprint $table): void {
            if (Schema::hasColumn('dyn_entities', 'print_settings_json')) {
                $table->dropColumn('print_settings_json');
            }
        });
    }
};
