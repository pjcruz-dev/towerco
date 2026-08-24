<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('entra_manager_licensed')->nullable()->after('entra_license_names');
            $table->string('entra_manager_license_label', 80)->nullable()->after('entra_manager_licensed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['entra_manager_licensed', 'entra_manager_license_label']);
        });
    }
};
