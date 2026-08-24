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
            $table->boolean('entra_licensed')->nullable()->after('entra_org_synced_at');
            $table->string('entra_license_label', 80)->nullable()->after('entra_licensed');
            $table->json('entra_license_names')->nullable()->after('entra_license_label');
            $table->index('entra_licensed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['entra_licensed']);
            $table->dropColumn(['entra_licensed', 'entra_license_label', 'entra_license_names']);
        });
    }
};
