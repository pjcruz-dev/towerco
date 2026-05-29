<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_tier', 32)->default('starter')->after('mfa_required');
            $table->string('subscription_status', 32)->default('active')->after('plan_tier');
            $table->unsignedInteger('seat_limit')->default(25)->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan_tier', 'subscription_status', 'seat_limit']);
        });
    }
};
