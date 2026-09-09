<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_extract_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->json('fields');
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('doc_extract_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->nullable()->constrained('doc_extract_templates')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('message')->nullable();
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('doc_extract_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('doc_extract_batches')->cascadeOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('doc_extract_templates')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_path', 512)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('scan_engine', 64)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('field_values')->nullable();
            $table->json('scan_meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'status']);
            $table->index(['created_at', 'purged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_extract_documents');
        Schema::dropIfExists('doc_extract_batches');
        Schema::dropIfExists('doc_extract_templates');
    }
};
