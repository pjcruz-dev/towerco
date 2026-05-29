<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollout_policy_bundles', function (Blueprint $table): void {
            if (! Schema::hasColumn('rollout_policy_bundles', 'email_notification_policies')) {
                $table->json('email_notification_policies')->nullable()->after('gate_approval_policies');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rollout_policy_bundles', function (Blueprint $table): void {
            if (Schema::hasColumn('rollout_policy_bundles', 'email_notification_policies')) {
                $table->dropColumn('email_notification_policies');
            }
        });
    }
};
