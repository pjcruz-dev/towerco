<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_activity_logs')) {
            return;
        }

        Schema::create('tenant_activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module', 50);
            $table->string('action', 80);
            $table->text('summary')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->string('entity_id', 36)->nullable();
            $table->string('entity_label', 255)->nullable();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at'], 'tenant_activity_created_idx');
            $table->index(['module', 'created_at'], 'tenant_activity_module_created_idx');
            $table->index(['actor_user_id', 'created_at'], 'tenant_activity_actor_created_idx');
            $table->index(['entity_type', 'entity_id'], 'tenant_activity_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_activity_logs');
    }
};
