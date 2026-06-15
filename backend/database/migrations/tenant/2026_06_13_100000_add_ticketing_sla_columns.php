<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticketing_tickets')) {
            return;
        }

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticketing_tickets', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('ticketing_tickets', 'sla_reminder_sent_at')) {
                $table->timestamp('sla_reminder_sent_at')->nullable()->after('sla_due_at');
            }
            if (! Schema::hasColumn('ticketing_tickets', 'sla_escalated_at')) {
                $table->timestamp('sla_escalated_at')->nullable()->after('sla_reminder_sent_at');
            }
        });

        if (Schema::hasColumn('ticketing_tickets', 'sla_due_at')) {
            Schema::table('ticketing_tickets', function (Blueprint $table): void {
                $table->index(['status', 'sla_due_at']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticketing_tickets')) {
            return;
        }

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->dropIndex(['status', 'sla_due_at']);
            $table->dropColumn(['sla_due_at', 'sla_reminder_sent_at', 'sla_escalated_at']);
        });
    }
};
