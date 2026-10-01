<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dyn_fields')) {
            return;
        }

        Schema::table('dyn_fields', function (Blueprint $table): void {
            if (! Schema::hasColumn('dyn_fields', 'is_key')) {
                $table->boolean('is_key')->default(false)->after('is_filterable');
            }
            if (! Schema::hasColumn('dyn_fields', 'calculate_totals')) {
                $table->boolean('calculate_totals')->default(false)->after('is_key');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dyn_fields')) {
            return;
        }

        Schema::table('dyn_fields', function (Blueprint $table): void {
            if (Schema::hasColumn('dyn_fields', 'calculate_totals')) {
                $table->dropColumn('calculate_totals');
            }
            if (Schema::hasColumn('dyn_fields', 'is_key')) {
                $table->dropColumn('is_key');
            }
        });
    }
};
