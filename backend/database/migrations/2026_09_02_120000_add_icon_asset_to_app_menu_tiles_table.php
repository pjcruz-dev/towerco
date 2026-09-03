<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_menu_tiles', function (Blueprint $table): void {
            $table->string('icon_asset', 512)->nullable()->after('icon');
            $table->string('icon_url', 1024)->nullable()->after('icon_asset');
        });
    }

    public function down(): void
    {
        Schema::table('app_menu_tiles', function (Blueprint $table): void {
            $table->dropColumn(['icon_asset', 'icon_url']);
        });
    }
};
