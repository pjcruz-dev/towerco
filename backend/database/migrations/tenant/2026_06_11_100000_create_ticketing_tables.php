<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticketing_tickets')) {
            return;
        }

        Schema::create('ticketing_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('ticket_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('category', 64)->nullable();
            $table->string('source_module', 64)->default('manual');
            $table->string('source_reference_type', 128)->nullable();
            $table->string('source_reference_id', 36)->nullable();
            $table->string('source_label', 255)->nullable();
            $table->foreignUuid('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique('ticket_number');
            $table->index(['status', 'created_at']);
            $table->index(['assignee_id', 'status']);
            $table->index(['source_module', 'source_reference_id']);
        });

        Schema::create('ticketing_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticketing_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->index('ticket_id');
        });

        Schema::create('ticketing_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->string('link_module', 64);
            $table->string('link_type', 128);
            $table->string('link_id', 36);
            $table->string('link_label', 255)->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'link_module']);
            $table->index(['link_module', 'link_type', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_links');
        Schema::dropIfExists('ticketing_attachments');
        Schema::dropIfExists('ticketing_comments');
        Schema::dropIfExists('ticketing_tickets');
    }
};
