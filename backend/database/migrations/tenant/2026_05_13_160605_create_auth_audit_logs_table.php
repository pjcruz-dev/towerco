<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('session_id')->nullable();
            $table->string('event', 100);
            $table->string('risk_level', 20)->default('low');
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('session_id')->references('id')->on('auth_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit_logs');
    }
};

