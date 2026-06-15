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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('billing_meter_starts_at')->nullable()->after('seat_limit');
            $table->string('billing_interval', 16)->default('monthly')->after('billing_meter_starts_at');
        });

        Schema::create('platform_billing_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('currency', 8)->default('USD');
            $table->decimal('default_annual_discount_percent', 5, 2)->default(20);
            $table->json('tier_overrides')->nullable();
            $table->timestamps();
        });

        DB::table('platform_billing_settings')->insertOrIgnore([
            'id' => 1,
            'currency' => 'USD',
            'default_annual_discount_percent' => 20,
            'tier_overrides' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('tenant_billing_rfi_completions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('rollout_id');
            $table->uuid('site_id')->nullable();
            $table->timestamp('rfi_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'rollout_id']);
            $table->index(['tenant_id', 'rfi_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_rfi_completions');
        Schema::dropIfExists('platform_billing_settings');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['billing_meter_starts_at', 'billing_interval']);
        });
    }
};
