<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dyn_field_groups')) {
            return;
        }

        Schema::table('dyn_field_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('dyn_field_groups', 'column_span')) {
                $table->unsignedTinyInteger('column_span')->default(12)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dyn_field_groups') || ! Schema::hasColumn('dyn_field_groups', 'column_span')) {
            return;
        }

        Schema::table('dyn_field_groups', function (Blueprint $table): void {
            $table->dropColumn('column_span');
        });
    }
};
