<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_prs')) {
            Schema::create('procurement_prs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->uuid('e_approval_submission_id')->nullable()->unique();
                $table->uuid('e_approval_form_id')->nullable();
                $table->foreignUuid('requestor_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('department', 64)->nullable();
                $table->string('urgency', 32)->nullable();
                $table->text('justification')->nullable();
                $table->decimal('estimated_total', 15, 2)->default(0);
                $table->string('currency', 8)->default('PHP');
                $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignUuid('rollout_id')->nullable()->constrained('rollout_programs')->nullOnDelete();
                $table->foreignUuid('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->uuid('boq_line_id')->nullable();
                $table->decimal('committed_po_amount', 15, 2)->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'ppr_status_created_idx');
                $table->index('requestor_id', 'ppr_requestor_idx');
                $table->index('project_id', 'ppr_project_idx');
                $table->index('rollout_id', 'ppr_rollout_idx');
            });
        }

        if (! Schema::hasTable('procurement_pr_lines')) {
            Schema::create('procurement_pr_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('pr_id')->constrained('procurement_prs')->cascadeOnDelete();
                $table->unsignedSmallInteger('line_order')->default(0);
                $table->string('description');
                $table->decimal('quantity', 12, 4)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['pr_id', 'line_order'], 'pprl_pr_order_idx');
            });
        }

        if (! Schema::hasTable('procurement_pr_attachments')) {
            Schema::create('procurement_pr_attachments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('pr_id')->constrained('procurement_prs')->cascadeOnDelete();
                $table->uuid('e_approval_attachment_id')->nullable();
                $table->string('field_name', 64)->default('quotes');
                $table->string('file_name');
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->timestamps();

                $table->index('pr_id', 'ppra_pr_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_pr_attachments');
        Schema::dropIfExists('procurement_pr_lines');
        Schema::dropIfExists('procurement_prs');
    }
};
