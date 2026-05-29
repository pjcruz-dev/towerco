<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_factors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type', 30)->default('totp');
            $table->text('secret_encrypted');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('mfa_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('factor_id')->nullable();
            $table->string('challenge_type', 30)->default('totp');
            $table->string('code_hash', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->foreign('session_id')->references('id')->on('auth_sessions')->cascadeOnDelete();
            $table->foreign('factor_id')->references('id')->on('mfa_factors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_challenges');
        Schema::dropIfExists('mfa_factors');
    }
};

