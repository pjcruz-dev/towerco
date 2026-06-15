<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('actor_user_id', 36)->nullable();
            $table->string('actor_email')->nullable();
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_audit_logs');
    }
};
