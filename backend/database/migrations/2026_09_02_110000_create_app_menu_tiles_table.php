<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_menu_tiles')) {
            return;
        }

        Schema::create('app_menu_tiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 128)->nullable();
            $table->string('title');
            $table->string('subtitle', 255)->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('accent', 32)->nullable();
            $table->string('href', 1024);
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique('key');
            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_menu_tiles');
    }
};
