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
            if (! Schema::hasColumn('tenant_activity_logs', 'category')) {
                $table->string('category', 32)->nullable()->after('action');
            }
            if (! Schema::hasColumn('tenant_activity_logs', 'severity')) {
                $table->string('severity', 16)->nullable()->after('category');
            }
            if (! Schema::hasColumn('tenant_activity_logs', 'reason')) {
                $table->text('reason')->nullable()->after('summary');
            }
        });

        // Indexes are best-effort; some tenant DBs may already have them from a partial deploy.
        try {
            Schema::table('tenant_activity_logs', function (Blueprint $table): void {
                $table->index(['category', 'created_at'], 'tenant_activity_category_created_idx');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('tenant_activity_logs', function (Blueprint $table): void {
                $table->index(['severity', 'created_at'], 'tenant_activity_severity_created_idx');
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_activity_logs')) {
            return;
        }

        Schema::table('tenant_activity_logs', function (Blueprint $table): void {
            try {
                $table->dropIndex('tenant_activity_category_created_idx');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('tenant_activity_severity_created_idx');
            } catch (\Throwable) {
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('tenant_activity_logs', 'reason') ? 'reason' : null,
                Schema::hasColumn('tenant_activity_logs', 'severity') ? 'severity' : null,
                Schema::hasColumn('tenant_activity_logs', 'category') ? 'category' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
