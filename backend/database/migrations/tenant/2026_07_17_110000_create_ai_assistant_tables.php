<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255)->nullable();
            $table->string('module_context', 64)->nullable();
            $table->string('page_path', 512)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'last_message_at']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content');
            $table->json('citations')->nullable();
            $table->string('model_name', 128)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('status', 32)->default('completed');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_knowledge_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope', 16);
            $table->string('module_key', 64)->nullable();
            $table->string('title', 255);
            $table->string('source_type', 32);
            $table->string('source_path', 1024)->nullable();
            $table->string('source_url', 1024)->nullable();
            $table->string('audience', 32)->default('tenant_user');
            $table->json('required_permissions')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'status', 'module_key']);
            $table->index(['status', 'published_at']);
        });

        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('knowledge_source_id')->constrained('ai_knowledge_sources')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->string('embedding_ref', 255)->nullable();
            $table->string('vector_id', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            $table->unique(['knowledge_source_id', 'chunk_index']);
            $table->index(['knowledge_source_id', 'checksum']);
        });

        Schema::create('ai_assistant_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignUuid('message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rating', 8);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_feedback');
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_sources');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
