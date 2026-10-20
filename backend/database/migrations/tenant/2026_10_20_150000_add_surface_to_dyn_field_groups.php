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
            if (! Schema::hasColumn('dyn_field_groups', 'surface')) {
                $table->string('surface', 32)->default('overview')->after('column_span');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dyn_field_groups') || ! Schema::hasColumn('dyn_field_groups', 'surface')) {
            return;
        }

        Schema::table('dyn_field_groups', function (Blueprint $table): void {
            $table->dropColumn('surface');
        });
    }
};
