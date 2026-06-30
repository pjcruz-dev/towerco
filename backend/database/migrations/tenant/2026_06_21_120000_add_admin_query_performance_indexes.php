<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_sessions', static function (Blueprint $table): void {
            $table->index(['user_id', 'revoked_at', 'last_seen_at'], 'auth_sessions_user_activity_idx');
        });

        Schema::table('auth_audit_logs', static function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'auth_audit_logs_user_created_idx');
        });

        Schema::table('users', static function (Blueprint $table): void {
            $table->index('name', 'users_name_idx');
        });

        $tableNames = config('permission.table_names');
        if (is_array($tableNames) && isset($tableNames['model_has_roles'])) {
            Schema::table($tableNames['model_has_roles'], static function (Blueprint $table): void {
                $table->index(['model_type', 'role_id'], 'model_has_roles_type_role_idx');
            });
        }

        Schema::table('mfa_factors', static function (Blueprint $table): void {
            $table->index(['user_id', 'disabled_at', 'verified_at'], 'mfa_factors_user_enrollment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('auth_sessions', static function (Blueprint $table): void {
            $table->dropIndex('auth_sessions_user_activity_idx');
        });

        Schema::table('auth_audit_logs', static function (Blueprint $table): void {
            $table->dropIndex('auth_audit_logs_user_created_idx');
        });

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropIndex('users_name_idx');
        });

        $tableNames = config('permission.table_names');
        if (is_array($tableNames) && isset($tableNames['model_has_roles'])) {
            Schema::table($tableNames['model_has_roles'], static function (Blueprint $table): void {
                $table->dropIndex('model_has_roles_type_role_idx');
            });
        }

        Schema::table('mfa_factors', static function (Blueprint $table): void {
            $table->dropIndex('mfa_factors_user_enrollment_idx');
        });
    }
};
