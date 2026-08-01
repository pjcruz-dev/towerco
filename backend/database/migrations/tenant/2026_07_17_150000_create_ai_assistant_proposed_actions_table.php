<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_proposed_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->string('action', 64);
            $table->string('status', 16)->default('pending');
            $table->json('payload');
            $table->json('preview')->nullable();
            $table->string('result_entity_type', 64)->nullable();
            $table->uuid('result_entity_id')->nullable();
            $table->json('result_meta')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['action', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_proposed_actions');
    }
};
