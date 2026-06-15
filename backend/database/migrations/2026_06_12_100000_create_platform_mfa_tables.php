<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('oauth_access_tokens', 'mfa_verified_at')) {
                $table->timestamp('mfa_verified_at')->nullable()->after('expires_at');
            }
        });

        Schema::create('platform_login_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending_mfa');
            $table->timestamp('mfa_verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('platform_mfa_factors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30)->default('totp');
            $table->text('secret_encrypted');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('platform_mfa_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('login_session_id');
            $table->uuid('factor_id')->nullable();
            $table->string('challenge_type', 30)->default('totp');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['login_session_id', 'status']);
            $table->foreign('login_session_id')->references('id')->on('platform_login_sessions')->cascadeOnDelete();
            $table->foreign('factor_id')->references('id')->on('platform_mfa_factors')->nullOnDelete();
        });

        Schema::create('platform_mfa_recovery_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'code_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_mfa_recovery_codes');
        Schema::dropIfExists('platform_mfa_challenges');
        Schema::dropIfExists('platform_mfa_factors');
        Schema::dropIfExists('platform_login_sessions');

        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_access_tokens', 'mfa_verified_at')) {
                $table->dropColumn('mfa_verified_at');
            }
        });
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
