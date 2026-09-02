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
            if (! Schema::hasColumn('dyn_field_groups', 'description')) {
                $table->string('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('dyn_field_groups', 'icon')) {
                $table->string('icon', 64)->nullable()->after('description');
            }
            if (! Schema::hasColumn('dyn_field_groups', 'start_collapsed')) {
                $table->boolean('start_collapsed')->default(false)->after('applies_to_view');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dyn_field_groups')) {
            return;
        }

        Schema::table('dyn_field_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('dyn_field_groups', 'start_collapsed')) {
                $table->dropColumn('start_collapsed');
            }
            if (Schema::hasColumn('dyn_field_groups', 'icon')) {
                $table->dropColumn('icon');
            }
            if (Schema::hasColumn('dyn_field_groups', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
