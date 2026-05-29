<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_approvals', function (Blueprint $table): void {
            $table->foreignUuid('rollout_program_id')
                ->nullable()
                ->after('project_id')
                ->constrained('rollout_programs')
                ->nullOnDelete();
            $table->json('attachment_file_ids')->nullable()->after('sla_risk');

            $table->index(['rollout_program_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('project_approvals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rollout_program_id');
            $table->dropColumn('attachment_file_ids');
        });
    }
};
