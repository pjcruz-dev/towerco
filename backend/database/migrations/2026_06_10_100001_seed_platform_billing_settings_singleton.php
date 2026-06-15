<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_billing_settings')) {
            return;
        }

        DB::table('platform_billing_settings')->insertOrIgnore([
            'id' => 1,
            'currency' => 'USD',
            'default_annual_discount_percent' => 20,
            'tier_overrides' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep singleton row — catalog defaults are not rolled back.
    }
};
