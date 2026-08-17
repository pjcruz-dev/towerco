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
            $table->foreignUuid('manager_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
            $table->string('entra_id', 64)->nullable()->after('manager_id');
            $table->string('job_title', 180)->nullable()->after('entra_id');
            $table->string('entra_manager_email', 255)->nullable()->after('job_title');
            $table->string('entra_manager_name', 180)->nullable()->after('entra_manager_email');
            $table->timestamp('entra_org_synced_at')->nullable()->after('entra_manager_name');
            $table->unique('entra_id');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['entra_id']);
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn([
                'entra_id',
                'job_title',
                'entra_manager_email',
                'entra_manager_name',
                'entra_org_synced_at',
            ]);
        });
    }
};
