<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('stripe_customer_id', 64)->nullable()->after('subscription_locked_at');
            $table->string('stripe_subscription_id', 64)->nullable()->after('stripe_customer_id');
            $table->string('stripe_price_id', 64)->nullable()->after('stripe_subscription_id');
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('type', 128);
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'stripe_price_id',
            ]);
        });
    }
};
