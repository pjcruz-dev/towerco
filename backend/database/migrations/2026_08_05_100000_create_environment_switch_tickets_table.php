<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_switch_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('token_hash', 64)->unique();
            $table->string('source_tenant_id');
            $table->string('target_tenant_id');
            $table->uuid('source_user_id');
            $table->string('actor_email', 255);
            $table->string('source_environment', 32)->nullable();
            $table->string('target_environment', 32)->nullable();
            $table->uuid('source_session_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('consumed_ip', 45)->nullable();
            $table->string('consumed_user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['expires_at']);
            $table->index(['target_tenant_id', 'consumed_at']);
            $table->foreign('source_tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('target_tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_switch_tickets');
    }
};
