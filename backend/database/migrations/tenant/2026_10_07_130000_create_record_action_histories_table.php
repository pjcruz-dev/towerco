<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('record_action_histories')) {
            return;
        }

        Schema::create('record_action_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module', 32);
            $table->string('entity_slug', 64);
            $table->string('entity_label', 255);
            $table->string('kind', 16);
            $table->unsignedInteger('record_count')->default(1);
            $table->uuid('record_id')->nullable();
            $table->string('field_label', 255)->nullable();
            $table->string('summary', 255);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('status', 16)->default('applied');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_slug', 'created_at'], 'record_action_entity_created_idx');
            $table->index(['actor_user_id', 'entity_slug', 'created_at'], 'record_action_actor_entity_idx');
            $table->index(['record_id'], 'record_action_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_action_histories');
    }
};
