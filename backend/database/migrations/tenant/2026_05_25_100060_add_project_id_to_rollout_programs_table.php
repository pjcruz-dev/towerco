<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollout_programs', function (Blueprint $table): void {
            $table->foreignUuid('project_id')->nullable()->after('site_id')->constrained('projects')->nullOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('rollout_programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
