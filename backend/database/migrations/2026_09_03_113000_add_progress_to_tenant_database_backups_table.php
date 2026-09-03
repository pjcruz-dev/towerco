<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenant_database_backups', function (Blueprint $table): void {
            $table->unsignedTinyInteger('progress_percent')->nullable()->after('status');
            $table->string('progress_message', 255)->nullable()->after('progress_percent');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenant_database_backups', function (Blueprint $table): void {
            $table->dropColumn(['progress_percent', 'progress_message']);
        });
    }
};
