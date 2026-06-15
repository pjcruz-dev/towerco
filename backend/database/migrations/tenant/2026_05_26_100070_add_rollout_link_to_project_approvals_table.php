<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_approvals')) {
            return;
        }

        if (! Schema::hasColumn('project_approvals', 'rollout_program_id')) {
            Schema::table('project_approvals', function (Blueprint $table): void {
                $table->foreignUuid('rollout_program_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('rollout_programs')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('project_approvals', 'attachment_file_ids')) {
            Schema::table('project_approvals', function (Blueprint $table): void {
                $table->json('attachment_file_ids')->nullable()->after('sla_risk');
            });
        }

        $indexName = 'project_approvals_rollout_program_id_status_index';
        $hasIndex = collect(Schema::getIndexes('project_approvals'))
            ->contains(static fn (array $index): bool => ($index['name'] ?? '') === $indexName);

        if (Schema::hasColumn('project_approvals', 'rollout_program_id') && ! $hasIndex) {
            Schema::table('project_approvals', function (Blueprint $table): void {
                $table->index(['rollout_program_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_approvals')) {
            return;
        }

        $indexName = 'project_approvals_rollout_program_id_status_index';
        $hasIndex = collect(Schema::getIndexes('project_approvals'))
            ->contains(static fn (array $index): bool => ($index['name'] ?? '') === $indexName);

        Schema::table('project_approvals', function (Blueprint $table) use ($hasIndex): void {
            if ($hasIndex) {
                $table->dropIndex(['rollout_program_id', 'status']);
            }
            if (Schema::hasColumn('project_approvals', 'rollout_program_id')) {
                $table->dropConstrainedForeignId('rollout_program_id');
            }
            if (Schema::hasColumn('project_approvals', 'attachment_file_ids')) {
                $table->dropColumn('attachment_file_ids');
            }
        });
    }
};
