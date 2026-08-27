<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'entra_manager_department')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('entra_manager_department', 180)->nullable()->after('entra_manager_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'entra_manager_department')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('entra_manager_department');
        });
    }
};
