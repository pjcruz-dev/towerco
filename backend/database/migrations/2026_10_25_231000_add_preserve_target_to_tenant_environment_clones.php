<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_environment_clones', function (Blueprint $table): void {
            $table->boolean('preserve_target')->default(false)->after('pause_source');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_environment_clones', function (Blueprint $table): void {
            $table->dropColumn('preserve_target');
        });
    }
};
