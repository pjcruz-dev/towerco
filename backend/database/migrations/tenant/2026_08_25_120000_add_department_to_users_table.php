<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'department')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('department', 180)->nullable()->after('job_title');
            $table->index('department');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'department')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['department']);
            $table->dropColumn('department');
        });
    }
};
