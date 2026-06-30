<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REVISIONS_TABLE = 'controlled_document_revisions';

    private const REVISIONS_DOC_REV_UNIQUE = 'cd_rev_doc_rev_uq';

    private const REVISIONS_DOC_STATUS_INDEX = 'cd_rev_doc_status_idx';

    public function up(): void
    {
        if (! Schema::hasTable('controlled_documents')) {
            Schema::create('controlled_documents', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_code', 128)->unique('cd_doc_code_uq');
                $table->string('title', 500);
                $table->string('document_type', 120)->nullable();
                $table->string('department', 120)->nullable();
                $table->unsignedInteger('current_revision')->default(0);
                $table->string('status', 32)->default('published');
                $table->date('effective_date')->nullable();
                $table->date('next_review_date')->nullable();
                $table->foreignUuid('e_approval_form_id')->nullable()->constrained('e_approval_forms')->nullOnDelete();
                $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'department'], 'cd_doc_status_dept_idx');
                $table->index('next_review_date', 'cd_doc_review_idx');
            });
        }

        if (! Schema::hasTable(self::REVISIONS_TABLE)) {
            Schema::create(self::REVISIONS_TABLE, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('controlled_document_id')->constrained('controlled_documents')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->text('change_summary')->nullable();
                $table->foreignUuid('e_approval_submission_id')->nullable()->unique('cd_rev_ea_sub_uq')->constrained('e_approval_submissions')->nullOnDelete();
                $table->string('stored_path', 512)->nullable();
                $table->string('original_filename', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('status', 32)->default('published');
                $table->date('effective_date')->nullable();
                $table->foreignUuid('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['controlled_document_id', 'revision_number'], self::REVISIONS_DOC_REV_UNIQUE);
                $table->index(['controlled_document_id', 'status'], self::REVISIONS_DOC_STATUS_INDEX);
            });
        }

        $this->ensureRevisionsIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::REVISIONS_TABLE);
        Schema::dropIfExists('controlled_documents');
    }

    private function ensureRevisionsIndexes(): void
    {
        if (! Schema::hasTable(self::REVISIONS_TABLE)) {
            return;
        }

        $indexes = collect(Schema::getIndexes(self::REVISIONS_TABLE));

        if (! $indexes->contains(static fn (array $index): bool => ($index['name'] ?? '') === self::REVISIONS_DOC_REV_UNIQUE)) {
            Schema::table(self::REVISIONS_TABLE, function (Blueprint $table): void {
                $table->unique(['controlled_document_id', 'revision_number'], self::REVISIONS_DOC_REV_UNIQUE);
            });
        }

        if (! $indexes->contains(static fn (array $index): bool => ($index['name'] ?? '') === self::REVISIONS_DOC_STATUS_INDEX)) {
            Schema::table(self::REVISIONS_TABLE, function (Blueprint $table): void {
                $table->index(['controlled_document_id', 'status'], self::REVISIONS_DOC_STATUS_INDEX);
            });
        }
    }
};
