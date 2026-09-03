<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_menu_groups')) {
            Schema::create('app_menu_groups', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('key', 128)->nullable();
                $table->string('title');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->timestamps();

                $table->unique('key');
                $table->index(['is_visible', 'sort_order']);
            });
        }

        if (Schema::hasTable('app_menu_tiles') && ! Schema::hasColumn('app_menu_tiles', 'group_id')) {
            Schema::table('app_menu_tiles', function (Blueprint $table): void {
                $table->uuid('group_id')->nullable()->after('key');
                $table->foreign('group_id')
                    ->references('id')
                    ->on('app_menu_groups')
                    ->nullOnDelete();
                $table->index(['group_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_menu_tiles') && Schema::hasColumn('app_menu_tiles', 'group_id')) {
            Schema::table('app_menu_tiles', function (Blueprint $table): void {
                $table->dropForeign(['group_id']);
                $table->dropIndex(['group_id', 'sort_order']);
                $table->dropColumn('group_id');
            });
        }

        Schema::dropIfExists('app_menu_groups');
    }
};
