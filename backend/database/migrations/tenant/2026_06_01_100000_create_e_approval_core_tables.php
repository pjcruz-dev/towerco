<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Approval tenant schema (ported from legacy/atcformbuiilder; users table excluded — uses tenant users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_approval_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general');
            $table->json('metadata_json')->nullable();
            $table->text('restricted_to')->nullable();
            $table->string('status', 20)->default('published');
            $table->unsignedInteger('schema_version')->default(1);
            $table->longText('published_snapshot')->nullable();
            $table->string('owner_code', 30)->default('GEN');
            $table->string('doc_type_code', 20)->default('F');
            $table->boolean('doc_no_custom_enabled')->default(false);
            $table->string('doc_no_template', 120)->nullable();
            $table->unsignedInteger('doc_no_seq_start')->nullable();
            $table->json('doc_no_seq_start_rules')->nullable();
            $table->string('brand_logo_url', 512)->nullable();
            $table->string('brand_primary_color', 32)->nullable();
            $table->text('related_form_ids')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('e_approval_form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('e_approval_forms')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('name', 100);
            $table->string('label');
            $table->string('semantic_type', 80)->nullable();
            $table->json('behavior')->nullable();
            $table->text('formula')->nullable();
            $table->json('validation')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('step_order')->default(0);

            $table->index('form_id');
        });

        Schema::create('e_approval_workflow_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('e_approval_forms')->cascadeOnDelete();

            $table->index('form_id');
        });

        Schema::create('e_approval_workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('e_approval_workflow_templates')->cascadeOnDelete();
            $table->unsignedInteger('step_order')->default(1);
            $table->string('approver_type', 50);
            $table->string('approver_id')->nullable();
            $table->json('condition')->nullable();

            $table->index('template_id');
        });

        Schema::create('e_approval_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_no', 50)->unique();
            $table->foreignUuid('form_id')->constrained('e_approval_forms');
            $table->foreignUuid('requestor_id')->constrained('users');
            $table->string('status', 50)->default('pending');
            $table->unsignedInteger('current_step')->default(1);
            $table->foreignUuid('parent_submission_id')->nullable()->constrained('e_approval_submissions')->nullOnDelete();
            $table->timestamps();

            $table->index(['requestor_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('e_approval_document_sequences', function (Blueprint $table) {
            $table->string('prefix', 30)->primary();
            $table->unsignedInteger('next_no');
        });

        Schema::create('e_approval_form_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->foreignUuid('field_id')->constrained('e_approval_form_fields')->cascadeOnDelete();
            $table->text('value')->nullable();

            $table->index('submission_id');
        });

        Schema::create('e_approval_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->string('field_name')->nullable();
            $table->string('file_path', 512);
            $table->string('file_name');
            $table->timestamps();

            $table->index('submission_id');
        });

        Schema::create('e_approval_request_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->foreignUuid('step_id')->constrained('e_approval_workflow_steps')->cascadeOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('signature')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['approver_id', 'status']);
            $table->index('submission_id');
        });

        Schema::create('e_approval_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('type', 80);
            $table->string('category', 16)->default('update');
            $table->foreignUuid('submission_id')->nullable()->constrained('e_approval_submissions')->nullOnDelete();
            $table->string('document_no', 64)->nullable();
            $table->string('form_name', 255)->nullable();
            $table->text('message')->nullable();
            $table->text('body_preview')->nullable();
            $table->string('href', 512)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['user_id', 'category', 'is_read']);
        });

        Schema::create('e_approval_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('target_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('e_approval_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->foreignUuid('parent_id')->nullable()->constrained('e_approval_comments')->nullOnDelete();
            $table->timestamps();

            $table->index('submission_id');
        });

        Schema::create('e_approval_submission_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->foreignUuid('requestor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('approver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'created_at']);
            $table->index(['approver_id', 'created_at']);
        });

        Schema::create('e_approval_user_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('e_approval_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('e_approval_document_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->foreignUuid('target_submission_id')->constrained('e_approval_submissions')->cascadeOnDelete();
            $table->string('link_type', 80);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('source_submission_id');
            $table->index('target_submission_id');
            $table->index('link_type');
        });

        Schema::create('e_approval_master_data_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('e_approval_master_data_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('set_id')->constrained('e_approval_master_data_sets')->cascadeOnDelete();
            $table->string('code', 120)->nullable();
            $table->string('label');
            $table->json('data_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['set_id', 'code']);
            $table->index(['set_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_master_data_rows');
        Schema::dropIfExists('e_approval_master_data_sets');
        Schema::dropIfExists('e_approval_document_links');
        Schema::dropIfExists('e_approval_settings');
        Schema::dropIfExists('e_approval_user_attachments');
        Schema::dropIfExists('e_approval_submission_followups');
        Schema::dropIfExists('e_approval_comments');
        Schema::dropIfExists('e_approval_audit_logs');
        Schema::dropIfExists('e_approval_notifications');
        Schema::dropIfExists('e_approval_request_approvals');
        Schema::dropIfExists('e_approval_attachments');
        Schema::dropIfExists('e_approval_form_values');
        Schema::dropIfExists('e_approval_document_sequences');
        Schema::dropIfExists('e_approval_submissions');
        Schema::dropIfExists('e_approval_workflow_steps');
        Schema::dropIfExists('e_approval_workflow_templates');
        Schema::dropIfExists('e_approval_form_fields');
        Schema::dropIfExists('e_approval_forms');
    }
};
