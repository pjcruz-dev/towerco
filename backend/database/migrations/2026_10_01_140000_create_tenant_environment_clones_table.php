<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_environment_clones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_tenant_id');
            $table->string('target_tenant_id')->nullable();
            $table->string('status', 32);
            $table->boolean('pause_source')->default(false);
            $table->boolean('cancel_requested')->default(false);
            $table->string('source_access_mode_before', 32)->nullable();
            $table->json('snapshot_counts')->nullable();
            $table->json('result_counts')->nullable();
            $table->unsignedInteger('files_total')->nullable();
            $table->unsignedInteger('files_copied')->default(0);
            $table->unsignedInteger('paused_schedules')->default(0);
            $table->text('error_message')->nullable();
            $table->string('actor_user_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source_tenant_id', 'status']);
            $table->index(['target_tenant_id', 'status']);
            $table->foreign('source_tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('target_tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_environment_clones');
    }
};
