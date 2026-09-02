<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_menu_settings')) {
            return;
        }

        Schema::create('app_menu_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            /** Desktop grid columns on /appmenu (3–6). Mobile stays 2. */
            $table->unsignedTinyInteger('grid_columns')->default(4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_menu_settings');
    }
};
