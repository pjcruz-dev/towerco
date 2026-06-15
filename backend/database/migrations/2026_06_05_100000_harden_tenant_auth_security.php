<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_sso_configs', function (Blueprint $table): void {
            $table->json('allowed_email_domains')->nullable()->after('group_mapping_rules');
            $table->boolean('disable_password_login_when_enabled')
                ->default(true)
                ->after('auto_provision_users');
        });

        DB::table('tenant_sso_configs')
            ->where('enabled', true)
            ->update(['disable_password_login_when_enabled' => false]);
    }

    public function down(): void
    {
        Schema::table('tenant_sso_configs', function (Blueprint $table): void {
            $table->dropColumn(['allowed_email_domains', 'disable_password_login_when_enabled']);
        });
    }
};
