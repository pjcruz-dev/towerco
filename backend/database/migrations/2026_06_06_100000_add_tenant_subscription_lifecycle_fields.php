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
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('past_due_grace_ends_at')->nullable()->after('trial_ends_at');
            $table->timestamp('canceled_at')->nullable()->after('past_due_grace_ends_at');
            $table->timestamp('subscription_locked_at')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'trial_ends_at',
                'past_due_grace_ends_at',
                'canceled_at',
                'subscription_locked_at',
            ]);
        });
    }
};
