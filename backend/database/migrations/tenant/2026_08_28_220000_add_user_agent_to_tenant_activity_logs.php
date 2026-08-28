<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_activity_logs')) {
            return;
        }

        Schema::table('tenant_activity_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_activity_logs', 'user_agent')) {
                $table->string('user_agent', 512)->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_activity_logs')) {
            return;
        }

        Schema::table('tenant_activity_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_activity_logs', 'user_agent')) {
                $table->dropColumn('user_agent');
            }
        });
    }
};
