<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_billing_audit_logs', function (Blueprint $table): void {
            $table->dropColumn('actor_user_id');
        });

        Schema::table('tenant_billing_audit_logs', function (Blueprint $table): void {
            $table->string('actor_user_id', 36)->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_billing_audit_logs', function (Blueprint $table): void {
            $table->dropColumn('actor_user_id');
        });

        Schema::table('tenant_billing_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_user_id')->nullable()->after('tenant_id');
        });
    }
};
