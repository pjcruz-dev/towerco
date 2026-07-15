<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VERSIONS_POLICY_VERSION_UNIQUE = 'eapv_policy_version_unique';

    private const VERSIONS_POLICY_STATUS_INDEX = 'eapv_policy_status_idx';

    private const WORKFLOW_COMPILED_SUBMISSION_INDEX = 'eaws_compiled_submission_idx';

    public function up(): void
    {
        if (! Schema::hasTable('e_approval_approval_policies')) {
            Schema::create('e_approval_approval_policies', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('key', 64)->unique('eap_policy_key_unique');
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('e_approval_approval_policy_versions')) {
            Schema::create('e_approval_approval_policy_versions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('policy_id')->constrained('e_approval_approval_policies')->cascadeOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->string('status', 32)->default('draft');
                $table->longText('config_json');
                $table->timestamp('published_at')->nullable();
                $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['policy_id', 'version_number'], self::VERSIONS_POLICY_VERSION_UNIQUE);
                $table->index(['policy_id', 'status'], self::VERSIONS_POLICY_STATUS_INDEX);
            });
        } else {
            Schema::table('e_approval_approval_policy_versions', function (Blueprint $table): void {
                if (! $this->indexExists('e_approval_approval_policy_versions', self::VERSIONS_POLICY_VERSION_UNIQUE)) {
                    $table->unique(['policy_id', 'version_number'], self::VERSIONS_POLICY_VERSION_UNIQUE);
                }

                if (! $this->indexExists('e_approval_approval_policy_versions', self::VERSIONS_POLICY_STATUS_INDEX)) {
                    $table->index(['policy_id', 'status'], self::VERSIONS_POLICY_STATUS_INDEX);
                }
            });
        }

        if (! Schema::hasColumn('e_approval_workflow_steps', 'compiled_for_submission_id')) {
            Schema::table('e_approval_workflow_steps', function (Blueprint $table): void {
                $table->uuid('compiled_for_submission_id')->nullable()->after('condition');
                $table->index('compiled_for_submission_id', self::WORKFLOW_COMPILED_SUBMISSION_INDEX);
            });
        }

        if (! Schema::hasColumn('e_approval_submissions', 'approval_policy_version_id')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                $table->foreignUuid('approval_policy_version_id')
                    ->nullable()
                    ->after('workflow_version_id')
                    ->constrained('e_approval_approval_policy_versions')
                    ->nullOnDelete();
                $table->string('approval_policy_label', 120)->nullable()->after('approval_policy_version_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('e_approval_submissions', 'approval_policy_version_id')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('approval_policy_version_id');
                $table->dropColumn('approval_policy_label');
            });
        }

        if (Schema::hasColumn('e_approval_workflow_steps', 'compiled_for_submission_id')) {
            Schema::table('e_approval_workflow_steps', function (Blueprint $table): void {
                $table->dropIndex(self::WORKFLOW_COMPILED_SUBMISSION_INDEX);
                $table->dropColumn('compiled_for_submission_id');
            });
        }

        Schema::dropIfExists('e_approval_approval_policy_versions');
        Schema::dropIfExists('e_approval_approval_policies');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        return collect($indexes)->contains(
            static fn (array $index): bool => ($index['name'] ?? '') === $indexName,
        );
    }
};
